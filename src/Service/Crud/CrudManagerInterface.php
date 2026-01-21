<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Service\Crud;

use Caeligo\ContactUsBundle\Model\ContactMessage;

/**
 * CRUD manager contract for contact messages.
 * Allows developers to swap implementation to customize business logic.
 */
interface CrudManagerInterface
{
    /**
     * @return iterable<ContactMessage>
     */
    public function list(int $page = 1, int $limit = 20): iterable;

    public function count(): int;

    public function find(int $id): ?ContactMessage;

    public function delete(ContactMessage $message): void;
}
