<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Event;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Contracts\EventDispatcher\Event;

class CrudShowEvent extends Event
{
    public const NAME = 'contact_us.crud.show';

    public function __construct(private ?ContactMessage $message, private int $id)
    {
    }

    public function getMessage(): ?ContactMessage
    {
        return $this->message;
    }

    public function setMessage(?ContactMessage $message): void
    {
        $this->message = $message;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
