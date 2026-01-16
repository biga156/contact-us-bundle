<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Storage;

use Caeligo\ContactUsBundle\Entity\ContactMessageEntity;
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine storage adapter
 */
class DoctrineStorage implements StorageInterface
{
    public function __construct(
        private ?EntityManagerInterface $entityManager = null
    ) {
    }

    public function save(ContactMessage $message): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Doctrine storage is not available. Install doctrine/orm and doctrine/doctrine-bundle.');
        }

        $entity = ContactMessageEntity::fromModel($message);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Update model with generated ID
        $message->setId($entity->getId());
    }

    public function findById(int $id): ?ContactMessage
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $entity = $this->entityManager->getRepository(ContactMessageEntity::class)->find($id);
        
        return $entity?->toModel();
    }

    public function findByVerificationToken(string $token): ?ContactMessage
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $entity = $this->entityManager->getRepository(ContactMessageEntity::class)
            ->findOneBy(['verificationToken' => $token]);
        
        return $entity?->toModel();
    }

    public function isAvailable(): bool
    {
        return $this->entityManager !== null;
    }
}
