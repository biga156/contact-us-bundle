<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Service;

use Caeligo\ContactUsBundle\Event\ContactEmailSentEvent;
use Caeligo\ContactUsBundle\Event\ContactPersistedEvent;
use Caeligo\ContactUsBundle\Event\ContactSubmittedEvent;
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
    public function __construct(
        private StorageInterface $storage,
        private ContactMailer $mailer,
        private HoneypotValidator $honeypotValidator,
        private TimingValidator $timingValidator,
        private ContactRateLimiter $rateLimiter,
        private EventDispatcherInterface $eventDispatcher,
        private string $storageMode = 'email'
    ) {
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

        // Store message if needed
        if (in_array($this->storageMode, ['database', 'both'], true)) {
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
}
