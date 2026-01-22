<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Storage;

use Caeligo\ContactUsBundle\Entity\ContactMessageEntity;
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine storage adapter
 * @template T of object
 */
class DoctrineStorage implements StorageInterface
{
    /** @var class-string<T> */
    private string $entityClass;

    /**
     * @param class-string<T>|null $entityClass
     */
    public function __construct(
        private ?EntityManagerInterface $entityManager = null,
        ?string $entityClass = null
    ) {
        /** @var class-string<T> $resolvedClass */
        $resolvedClass = $entityClass ?? ContactMessageEntity::class;
        $this->entityClass = $resolvedClass;
    }

    public function save(ContactMessage $message): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Doctrine storage is not available. Install doctrine/orm and doctrine/doctrine-bundle.');
        }

        if ($this->entityManager === null) {
            throw new \RuntimeException('EntityManager is not available.');
        }

        // Check if this is an update (message has an ID)
        $entity = null;
        if ($message->getId() !== null) {
            /** @var T|null $entity */
            $entity = $this->entityManager->getRepository($this->entityClass)->find($message->getId());
        }

        // If it's the default bundle entity
        if ($this->entityClass === ContactMessageEntity::class) {
            if ($entity instanceof ContactMessageEntity) {
                // Update existing entity
                $entity->setData($message->getData());
                $entity->setIpAddress($message->getIpAddress());
                $entity->setUserAgent($message->getUserAgent());
                $entity->setVerified($message->isVerified());
                $entity->setVerificationToken($message->getVerificationToken());
                $entity->setVerifiedAt($message->getVerifiedAt());
            } else {
                // Create new entity
                $entity = ContactMessageEntity::fromModel($message);
            }
        } else {
            // For custom entities
            if ($entity === null) {
                $entity = new $this->entityClass();
            }
            // Map ContactMessage data to custom entity
            // This assumes the entity has compatible properties/setters
            if (method_exists($entity, 'setData')) {
                $entity->setData($message->getData());
            } else {
                // Map individual fields if setData is not available
                foreach ($message->getData() as $key => $value) {
                    $setter = 'set' . str_replace('_', '', ucwords($key, '_'));
                    if (method_exists($entity, $setter)) {
                        $entity->$setter($value);
                    }
                }
            }
            if (method_exists($entity, 'setIpAddress')) {
                $entity->setIpAddress($message->getIpAddress());
            }
            if (method_exists($entity, 'setUserAgent')) {
                $entity->setUserAgent($message->getUserAgent());
            }
            if (method_exists($entity, 'setVerified')) {
                $entity->setVerified($message->isVerified());
            }
            if (method_exists($entity, 'setVerificationToken')) {
                $entity->setVerificationToken($message->getVerificationToken());
            }
            if (method_exists($entity, 'setVerifiedAt')) {
                $entity->setVerifiedAt($message->getVerifiedAt());
            }
        }
        
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        // Update model with generated ID
        if (method_exists($entity, 'getId')) {
            $message->setId($entity->getId());
        }
    }

    public function findById(int $id): ?ContactMessage
    {
        if (!$this->isAvailable() || $this->entityManager === null) {
            return null;
        }

        /** @var T|null $entity */
        $entity = $this->entityManager->getRepository($this->entityClass)->find($id);
        
        if ($entity === null) {
            return null;
        }

        // If it's the default entity, use toModel() method
        if ($entity instanceof ContactMessageEntity) {
            return $entity->toModel();
        }

        // For custom entities, create ContactMessage from entity
        $model = new ContactMessage();
        if (method_exists($entity, 'getData')) {
            $model->setData($entity->getData() ?? []);
        } else {
            // Map individual properties to data array
            $model->setData([
                'name' => method_exists($entity, 'getTitle') ? ($entity->getTitle() ?? '') : '',
                'email' => method_exists($entity, 'getEmail') ? ($entity->getEmail() ?? '') : '',
                'subject' => method_exists($entity, 'getSubtitle') ? ($entity->getSubtitle() ?? '') : '',
                'message' => method_exists($entity, 'getBody') ? ($entity->getBody() ?? '') : '',
                'phone' => method_exists($entity, 'getPhone') ? ($entity->getPhone() ?? '') : '',
            ]);
        }
        if (method_exists($entity, 'getIpAddress')) {
            $model->setIpAddress($entity->getIpAddress());
        }
        if (method_exists($entity, 'getUserAgent')) {
            $model->setUserAgent($entity->getUserAgent());
        }
        if (method_exists($entity, 'getId')) {
            $model->setId($entity->getId());
        }

        return $model;
    }

    public function findByVerificationToken(string $token): ?ContactMessage
    {
        if (!$this->isAvailable() || $this->entityManager === null) {
            return null;
        }

        /** @var T|null $entity */
        $entity = $this->entityManager->getRepository($this->entityClass)
            ->findOneBy(['verificationToken' => $token]);
        
        if ($entity === null) {
            return null;
        }

        if ($entity instanceof ContactMessageEntity) {
            return $entity->toModel();
        }

        if (method_exists($entity, 'getId')) {
            return $this->findById($entity->getId());
        }
        
        return null;
    }

    public function isAvailable(): bool
    {
        return $this->entityManager !== null;
    }
}
