<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Service;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Service for sending contact form emails
 */
class ContactMailer
{
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
        private bool $sendCopyToSender = false
    ) {
    }

    /**
     * Send contact notification email
     * 
     * @return array<string> List of recipient addresses
     */
    public function send(ContactMessage $message): array
    {
        $data = $message->getData();
        $subject = $this->buildSubject($data);

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
            ]);

        // Add recipients
        foreach ($this->recipients as $recipient) {
            $email->addTo($recipient);
        }

        // Optionally send the same notification to the sender
        if ($this->sendCopyToSender && !empty($data['email'])) {
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
     * Send auto-reply confirmation email to the form sender
     */
    private function sendAutoReply(ContactMessage $message): void
    {
        $data = $message->getData();
        
        if (empty($data['email'])) {
            return;
        }

        $autoReply = (new TemplatedEmail())
            ->from(new Address(
                $this->autoReplyFrom ?? $this->resolveFromEmail($data),
                $this->fromName
            ))
            ->to(new Address($data['email'], $data['name'] ?? ''))
            ->subject('Thank you for contacting us')
            ->htmlTemplate('@ContactUs/email/contact_auto_reply.html.twig')
            ->textTemplate('@ContactUs/email/contact_auto_reply.txt.twig')
            ->context([
                'message' => $message,
                'data' => $data,
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
