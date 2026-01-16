<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Storage;

use Caeligo\ContactUsBundle\Model\ContactMessage;

/**
 * Null storage adapter - does not persist messages
 */
class NullStorage implements StorageInterface
{
    public function save(ContactMessage $message): void
    {
        // No-op
    }

    public function findById(int $id): ?ContactMessage
    {
        return null;
    }

    public function findByVerificationToken(string $token): ?ContactMessage
    {
        return null;
    }

    public function isAvailable(): bool
    {
        return false;
    }
}
