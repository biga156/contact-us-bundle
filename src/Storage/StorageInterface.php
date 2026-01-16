<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Storage;

use Caeligo\ContactUsBundle\Model\ContactMessage;

interface StorageInterface
{
    /**
     * Persist a contact message
     */
    public function save(ContactMessage $message): void;

    /**
     * Find a message by ID
     */
    public function findById(int $id): ?ContactMessage;

    /**
     * Find a message by verification token
     */
    public function findByVerificationToken(string $token): ?ContactMessage;

    /**
     * Check if storage is available/configured
     */
    public function isAvailable(): bool;
}
