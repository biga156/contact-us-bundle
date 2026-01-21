<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Event;

use Caeligo\ContactUsBundle\Model\ContactMessage;
use Symfony\Contracts\EventDispatcher\Event;

class CrudListEvent extends Event
{
    public const NAME = 'contact_us.crud.list';

    /** @param iterable<ContactMessage> $messages */
    public function __construct(
        private iterable $messages,
        private int $page,
        private int $limit,
        private int $total
    ) {
    }

    /** @return iterable<ContactMessage> */
    public function getMessages(): iterable
    {
        return $this->messages;
    }

    /** @param iterable<ContactMessage> $messages */
    public function setMessages(iterable $messages): void
    {
        $this->messages = $messages;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
