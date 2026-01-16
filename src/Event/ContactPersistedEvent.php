<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Event;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched after a contact message is persisted to storage
 */
class ContactPersistedEvent extends Event
{
    public const NAME = 'contact_us.persisted';

    public function __construct(
        private ContactMessage $message
    ) {
    }

    public function getMessage(): ContactMessage
    {
        return $this->message;
    }
}
