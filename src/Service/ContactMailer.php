<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Service;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service for sending contact form emails
 */
class ContactMailer
{
    private const DEFAULT_LOCALE = 'en';

    /**
     * @param array<string> $recipients
     */
    public function __construct(
        private MailerInterface $mailer,
        private array $recipients,
        private string $subjectPrefix = '[Contact Form]',
        private ?string $fromEmail = null,
        private string $fromName = 'Contact Form',
        private bool $enableAutoReply = false,
        private ?string $autoReplyFrom = null,
        private bool $sendCopyToSender = false,
        private ?UrlGeneratorInterface $urlGenerator = null,
        private ?TranslatorInterface $translator = null,
        private string $defaultLocale = 'en'
    ) {
    }

    /**
     * Translate a key with fallback to English
     */
    private function trans(string $key, array $params = []): string
    {
        if ($this->translator === null) {
            return $this->extractPlainText($key);
        }

        // Try current locale first
        $translated = $this->translator->trans($key, $params, 'contact_us');
        
        // If translation returns the key unchanged, try English
        if ($translated === $key) {
            $translated = $this->translator->trans($key, $params, 'contact_us', self::DEFAULT_LOCALE);
        }
        
        // If still unchanged, extract plain text from key
        if ($translated === $key) {
            return $this->extractPlainText($key);
        }
        
        return $translated;
    }

    /**
     * Extract human-readable text from translation key
     */
    private function extractPlainText(string $key): string
    {
        $parts = explode('.', $key);
        $lastPart = end($parts);
        $text = preg_replace('/[_-]/', ' ', $lastPart);
        return ucwords($text ?? '');
    }

    /**
     * Get all translated labels for email templates
     * @return array<string, string>
     */
    private function getTranslatedLabels(): array
    {
        return [
            // Form field labels
            'label_name' => $this->trans('contact.form.name'),
            'label_email' => $this->trans('contact.form.email'),
            'label_phone' => $this->trans('contact.form.phone'),
            'label_subject' => $this->trans('contact.form.subject'),
            'label_message' => $this->trans('contact.form.message'),
            
            // Verification email
            'verification_title' => $this->trans('contact.verification.email_title'),
            'verification_intro' => $this->trans('contact.verification.email_intro'),
            'verification_your_message' => $this->trans('contact.verification.your_message'),
            'verification_warning_title' => $this->trans('contact.verification.warning_title'),
            'verification_warning_text' => $this->trans('contact.verification.warning_text'),
            'verification_button' => $this->trans('contact.verification.verify_button'),
            'verification_link_fallback' => $this->trans('contact.verification.link_fallback'),
            'verification_footer' => $this->trans('contact.verification.footer_text'),
            'verification_not_you' => $this->trans('contact.verification.not_you'),
            
            // Notification email
            'notification_title' => $this->trans('contact.notification.title'),
            'notification_intro' => $this->trans('contact.notification.intro'),
            'notification_footer' => $this->trans('contact.notification.footer'),
        ];
    }

    /**
     * Send contact notification email to admin(s)
     * 
     * @return array<string> List of recipient addresses
     */
    public function send(ContactMessage $message): array
    {
        $data = $message->getData();
        $subject = $this->buildSubject($data);
        $labels = $this->getTranslatedLabels();

        $email = (new TemplatedEmail())
            ->from(new Address(
                $this->resolveFromEmail($data),
                $this->fromName
            ))
            ->subject($subject)
            ->htmlTemplate('@ContactUs/email/contact_notification.html.twig')
            ->textTemplate('@ContactUs/email/contact_notification.txt.twig')
            ->context([
                'message' => $message,
                'data' => $data,
                'subject' => $subject,
                'labels' => $labels,
            ]);

        // Add recipients
        foreach ($this->recipients as $recipient) {
            $email->addTo($recipient);
        }

        // Send copy to sender ONLY if:
        // 1. sendCopyToSender is enabled AND
        // 2. Email verification was NOT used (message is not verified)
        // If email verification is enabled, the sender already received the verification email
        // which contains their message content, so no need to send another copy
        if ($this->sendCopyToSender && !empty($data['email']) && !$message->isVerified()) {
            $email->addCc(new Address($data['email'], $data['name'] ?? ''));
        }

        // Set reply-to if email is provided in form
        if (!empty($data['email'])) {
            $replyToName = $data['name'] ?? '';
            $email->replyTo(new Address($data['email'], $replyToName));
        }

        $this->mailer->send($email);

        // Send auto-reply to sender if enabled
        if ($this->enableAutoReply && !empty($data['email'])) {
            $this->sendAutoReply($message);
        }

        return $this->recipients;
    }

    /**
     * Send verification email to the form sender
     * Contains the message content AND a verification link
     * Admin will only be notified after verification
     */
    public function sendVerificationEmail(ContactMessage $message, string $token): void
    {
        $data = $message->getData();

        if (empty($data['email'])) {
            throw new \RuntimeException('Cannot send verification email: sender email is missing');
        }

        $labels = $this->getTranslatedLabels();
        $subject = $this->buildSubject($data) . ' - ' . $this->trans('contact.verification.email_subject');
        
        // Generate verification URL
        $verificationUrl = $this->generateVerificationUrl($token);

        $email = (new TemplatedEmail())
            ->from(new Address(
                $this->resolveFromEmail($data),
                $this->fromName
            ))
            ->to(new Address($data['email'], $data['name'] ?? ''))
            ->subject($subject)
            ->htmlTemplate('@ContactUs/email/contact_verification.html.twig')
            ->textTemplate('@ContactUs/email/contact_verification.txt.twig')
            ->context([
                'message' => $message,
                'data' => $data,
                'subject' => $subject,
                'verificationUrl' => $verificationUrl,
                'token' => $token,
                'labels' => $labels,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Generate verification URL
     */
    private function generateVerificationUrl(string $token): string
    {
        if ($this->urlGenerator !== null) {
            return $this->urlGenerator->generate(
                'contact_us_verify',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        // Fallback: construct URL manually (less ideal but works without router)
        $baseUrl = $_SERVER['REQUEST_SCHEME'] ?? 'https';
        $baseUrl .= '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $baseUrl . '/contact/verify/' . $token;
    }

    /**
     * Send auto-reply confirmation email to the form sender
     */
    private function sendAutoReply(ContactMessage $message): void
    {
        $data = $message->getData();
        
        if (empty($data['email'])) {
            return;
        }

        $labels = $this->getTranslatedLabels();

        $autoReply = (new TemplatedEmail())
            ->from(new Address(
                $this->autoReplyFrom ?? $this->resolveFromEmail($data),
                $this->fromName
            ))
            ->to(new Address($data['email'], $data['name'] ?? ''))
            ->subject($this->trans('contact.auto_reply.subject'))
            ->htmlTemplate('@ContactUs/email/contact_auto_reply.html.twig')
            ->textTemplate('@ContactUs/email/contact_auto_reply.txt.twig')
            ->context([
                'message' => $message,
                'data' => $data,
                'labels' => $labels,
            ]);

        $this->mailer->send($autoReply);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSubject(array $data): string
    {
        $subject = $this->subjectPrefix;

        // Add custom subject if provided
        if (!empty($data['subject'])) {
            $subject .= ' ' . $data['subject'];
        } elseif (!empty($data['name'])) {
            $subject .= ' from ' . $data['name'];
        } else {
            $subject .= ' New message';
        }

        return $subject;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveFromEmail(array $data): string
    {
        $candidate = $this->fromEmail ?: ($data['email'] ?? null);

        // Fallback to sensible default to avoid invalid sender
        return $candidate ?: 'noreply@example.com';
    }
}
