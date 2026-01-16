<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Event;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a contact form is submitted (before processing)
 */
class ContactSubmittedEvent extends Event
{
    public const NAME = 'contact_us.submitted';

    public function __construct(
        private ContactMessage $message,
        private bool $shouldProcess = true
    ) {
    }

    public function getMessage(): ContactMessage
    {
        return $this->message;
    }

    public function shouldProcess(): bool
    {
        return $this->shouldProcess;
    }

    public function preventProcessing(): void
    {
        $this->shouldProcess = false;
    }
}
