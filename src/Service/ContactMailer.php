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
        private string $fromName = 'Contact Form'
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
                $this->fromEmail ?? $data['email'] ?? 'noreply@example.com',
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

        // Set reply-to if email is provided in form
        if (!empty($data['email'])) {
            $replyToName = $data['name'] ?? '';
            $email->replyTo(new Address($data['email'], $replyToName));
        }

        $this->mailer->send($email);

        return $this->recipients;
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
}
