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
    private string $entityClass;

    public function __construct(
        private ?EntityManagerInterface $entityManager = null,
        ?string $entityClass = null
    ) {
        $this->entityClass = $entityClass ?? ContactMessageEntity::class;
    }

    public function save(ContactMessage $message): void
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Doctrine storage is not available. Install doctrine/orm and doctrine/doctrine-bundle.');
        }

        // If it's the default bundle entity, use the fromModel factory method
        if ($this->entityClass === ContactMessageEntity::class) {
            $entity = ContactMessageEntity::fromModel($message);
        } else {
            // For custom entities, create instance and map fields manually
            $entity = new $this->entityClass();
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
        if (!$this->isAvailable()) {
            return null;
        }

        $entity = $this->entityManager->getRepository($this->entityClass)->find($id);
        
        if ($entity === null) {
            return null;
        }

        // If it's the default entity, use toModel() method
        if ($this->entityClass === ContactMessageEntity::class) {
            return $entity->toModel();
        }

        // For custom entities, create ContactMessage from entity
        $model = new ContactMessage();
        if (method_exists($entity, 'getData')) {
            $model->setData($entity->getData() ?? []);
        } else {
            // Map individual properties to data array
            $model->setData([
                'name' => $entity->getTitle() ?? '',
                'email' => $entity->getEmail() ?? '',
                'subject' => $entity->getSubtitle() ?? '',
                'message' => $entity->getBody() ?? '',
                'phone' => $entity->getPhone() ?? '',
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
        if (!$this->isAvailable()) {
            return null;
        }

        $entity = $this->entityManager->getRepository($this->entityClass)
            ->findOneBy(['verificationToken' => $token]);
        
        if ($entity === null) {
            return null;
        }

        if ($this->entityClass === ContactMessageEntity::class) {
            return $entity->toModel();
        }

        return $this->findById($entity->getId());
    }

    public function isAvailable(): bool
    {
        return $this->entityManager !== null;
    }
}
