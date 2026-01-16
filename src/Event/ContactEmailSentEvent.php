<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Event;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after a contact email is sent
 */
class ContactEmailSentEvent extends Event
{
    public const NAME = 'contact_us.email_sent';

    /**
     * @param array<string> $recipients
     */
    public function __construct(
        private ContactMessage $message,
        private array $recipients
    ) {
    }

    public function getMessage(): ContactMessage
    {
        return $this->message;
    }

    /**
     * @return array<string>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }
}
