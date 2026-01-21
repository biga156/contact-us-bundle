<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Event;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a contact message is verified by the sender
 */
class ContactVerifiedEvent extends Event
{
    public const NAME = 'contact_us.verified';

    public function __construct(
        private ContactMessage $message
    ) {
    }

    public function getMessage(): ContactMessage
    {
        return $this->message;
    }
}
