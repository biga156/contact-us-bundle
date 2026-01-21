<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Service;

use Caeligo\ContactUsBundle\Event\ContactEmailSentEvent;
use Caeligo\ContactUsBundle\Event\ContactPersistedEvent;
use Caeligo\ContactUsBundle\Event\ContactSubmittedEvent;
use Caeligo\ContactUsBundle\Event\ContactVerifiedEvent;
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Caeligo\ContactUsBundle\SpamProtection\ContactRateLimiter;
use Caeligo\ContactUsBundle\SpamProtection\HoneypotValidator;
use Caeligo\ContactUsBundle\SpamProtection\TimingValidator;
use Caeligo\ContactUsBundle\Storage\StorageInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service for processing contact form submissions
 */
class ContactSubmissionService
{
    /** @var array{enabled: bool, token_ttl: string} */
    private array $emailVerificationConfig;

    /**
     * @param array{enabled: bool, token_ttl: string} $emailVerificationConfig
     */
    public function __construct(
        private StorageInterface $storage,
        private ContactMailer $mailer,
        private HoneypotValidator $honeypotValidator,
        private TimingValidator $timingValidator,
        private ContactRateLimiter $rateLimiter,
        private EventDispatcherInterface $eventDispatcher,
        private string $storageMode = 'email',
        array $emailVerificationConfig = ['enabled' => false, 'token_ttl' => '24 hours']
    ) {
        $this->emailVerificationConfig = $emailVerificationConfig;
    }

    /**
     * Check if email verification is enabled and applicable
     */
    public function isEmailVerificationEnabled(): bool
    {
        // Email verification only works in 'both' mode (needs database to store pending + email to verify)
        return $this->emailVerificationConfig['enabled'] && $this->storageMode === 'both';
    }

    /**
     * Process a contact form submission
     * 
     * @throws \RuntimeException If spam protection fails or rate limit exceeded
     */
    public function process(FormInterface $form, Request $request): ContactMessage
    {
        // Validate honeypot
        if (!$this->honeypotValidator->validate($form)) {
            throw new \RuntimeException('Spam detected: honeypot validation failed');
        }

        // Validate timing
        if (!$this->timingValidator->validate($form)) {
            throw new \RuntimeException('Spam detected: form submitted too quickly');
        }

        // Check rate limit
        if (!$this->rateLimiter->isAllowed()) {
            $retryAfter = $this->rateLimiter->getRetryAfter();
            throw new \RuntimeException(
                sprintf('Rate limit exceeded. Please try again after %s', 
                    $retryAfter?->format('Y-m-d H:i:s') ?? 'a while')
            );
        }

        // Create message from form data
        $message = $this->createMessageFromForm($form, $request);

        // Dispatch pre-processing event
        $event = new ContactSubmittedEvent($message);
        $this->eventDispatcher->dispatch($event, ContactSubmittedEvent::NAME);

        if (!$event->shouldProcess()) {
            throw new \RuntimeException('Message processing was prevented by an event listener');
        }

        // Email verification flow (only in 'both' mode)
        if ($this->isEmailVerificationEnabled()) {
            // Generate verification token
            $token = bin2hex(random_bytes(32));
            $message->setVerificationToken($token);
            $message->setVerified(false);

            // Store message as unverified
            $this->storage->save($message);
            $this->eventDispatcher->dispatch(
                new ContactPersistedEvent($message),
                ContactPersistedEvent::NAME
            );

            // Send verification email to sender (with message content + verification link)
            // NO admin email at this point!
            $this->mailer->sendVerificationEmail($message, $token);

            return $message;
        }

        // Standard flow (no email verification)
        
        // Store message if needed
        if (in_array($this->storageMode, ['database', 'both'], true)) {
            $message->setVerified(true); // Mark as verified immediately (no verification required)
            $this->storage->save($message);
            $this->eventDispatcher->dispatch(
                new ContactPersistedEvent($message),
                ContactPersistedEvent::NAME
            );
        }

        // Send email if needed
        if (in_array($this->storageMode, ['email', 'both'], true)) {
            $recipients = $this->mailer->send($message);
            $this->eventDispatcher->dispatch(
                new ContactEmailSentEvent($message, $recipients),
                ContactEmailSentEvent::NAME
            );
        }

        return $message;
    }

    /**
     * Verify a message by token and send admin notification
     * 
     * @throws \RuntimeException If token is invalid or expired
     */
    public function verifyMessage(string $token): ContactMessage
    {
        $message = $this->storage->findByVerificationToken($token);

        if ($message === null) {
            throw new \RuntimeException('Invalid or expired verification token');
        }

        if ($message->isVerified()) {
            throw new \RuntimeException('Message has already been verified');
        }

        // Check token expiration
        $createdAt = $message->getCreatedAt();
        $ttl = $this->parseInterval($this->emailVerificationConfig['token_ttl']);
        $expiresAt = $createdAt?->modify('+' . $ttl . ' seconds');

        if ($expiresAt !== null && $expiresAt < new \DateTimeImmutable()) {
            throw new \RuntimeException('Verification token has expired');
        }

        // Mark as verified
        $message->setVerified(true);
        $message->setVerifiedAt(new \DateTimeImmutable());
        $this->storage->save($message);

        // NOW send the admin notification
        $recipients = $this->mailer->send($message);
        $this->eventDispatcher->dispatch(
            new ContactEmailSentEvent($message, $recipients),
            ContactEmailSentEvent::NAME
        );

        // Dispatch verification event
        $this->eventDispatcher->dispatch(
            new ContactVerifiedEvent($message),
            ContactVerifiedEvent::NAME
        );

        return $message;
    }

    private function createMessageFromForm(FormInterface $form, Request $request): ContactMessage
    {
        $message = new ContactMessage();
        
        // Extract form data (excluding honeypot and timing fields)
        $data = [];
        foreach ($form->all() as $name => $field) {
            if (!in_array($name, ['email_confirm', '_form_token_time', '_token'], true)) {
                $data[$name] = $field->getData();
            }
        }
        
        $message->setData($data);
        $message->setIpAddress($request->getClientIp());
        $message->setUserAgent($request->headers->get('User-Agent'));

        return $message;
    }

    /**
     * Parse interval string to seconds
     */
    private function parseInterval(string $interval): int
    {
        // Parse strings like "24 hours", "1 hour", "30 minutes"
        if (preg_match('/^(\d+)\s*(hour|hours|minute|minutes|day|days)$/i', $interval, $matches)) {
            $value = (int) $matches[1];
            $unit = strtolower($matches[2]);

            return match ($unit) {
                'minute', 'minutes' => $value * 60,
                'hour', 'hours' => $value * 3600,
                'day', 'days' => $value * 86400,
                default => 86400, // 24 hours default
            };
        }

        return 86400; // 24 hours default
    }
}
