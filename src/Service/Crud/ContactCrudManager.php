<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Service\Crud;

use Caeligo\ContactUsBundle\Entity\ContactMessageEntity;
use Caeligo\ContactUsBundle\Model\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Default CRUD manager backed by Doctrine.
 * Designed for the bundle's ContactMessageEntity, but can be replaced via DI alias.
 */
class ContactCrudManager implements CrudManagerInterface
{
    /**
     * @param class-string<object>|null $entityClass
     */
    public function __construct(
        private ?EntityManagerInterface $entityManager = null,
        private ?string $entityClass = null
    ) {
    }

    public function list(int $page = 1, int $limit = 20): iterable
    {
        $em = $this->requireEntityManager();
        $metadata = $this->getMetadata();

        $class = $this->getEntityClass();

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from($class, 'e')
            ->setFirstResult(max(0, $page - 1) * $limit)
            ->setMaxResults($limit);

        if ($metadata->hasField('createdAt')) {
            $qb->orderBy('e.createdAt', 'DESC');
        } else {
            $qb->orderBy('e.id', 'DESC');
        }

        $entities = $qb->getQuery()->getResult();

        return array_map(fn (object $entity) => $this->toModel($entity), $entities);
    }

    public function count(): int
    {
        $em = $this->requireEntityManager();

        $class = $this->getEntityClass();

        $qb = $em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function find(int $id): ?ContactMessage
    {
        $em = $this->requireEntityManager();
        $class = $this->getEntityClass();
        /** @var object|null $entity */
        $entity = $em->find($class, $id);

        if ($entity === null) {
            return null;
        }

        return $this->toModel($entity);
    }

    public function delete(ContactMessage $message): void
    {
        $em = $this->requireEntityManager();
        $id = $message->getId();

        if ($id === null) {
            return;
        }

        $class = $this->getEntityClass();
        /** @var object|null $entity */
        $entity = $em->find($class, $id);
        if ($entity === null) {
            return;
        }

        $em->remove($entity);
        $em->flush();
    }

    private function toModel(object $entity): ContactMessage
    {
        if ($entity instanceof ContactMessageEntity) {
            return $entity->toModel();
        }

        $model = new ContactMessage();

        if (method_exists($entity, 'getId')) {
            $model->setId($entity->getId());
        }
        if (method_exists($entity, 'getCreatedAt') && $entity->getCreatedAt() instanceof \DateTimeInterface) {
            /** @var \DateTimeInterface $createdAt */
            $createdAt = $entity->getCreatedAt();
            $model->setCreatedAt(\DateTimeImmutable::createFromInterface($createdAt));
        }
        if (method_exists($entity, 'getData')) {
            $model->setData($entity->getData() ?? []);
        }
        if (method_exists($entity, 'getIpAddress')) {
            $model->setIpAddress($entity->getIpAddress());
        }
        if (method_exists($entity, 'getUserAgent')) {
            $model->setUserAgent($entity->getUserAgent());
        }

        // Fallback: try to hydrate common fields from individual getters
        if (empty($model->getData())) {
            $model->setData([
                'name' => method_exists($entity, 'getName') ? ($entity->getName() ?? '') : ($model->get('name') ?? ''),
                'email' => method_exists($entity, 'getEmail') ? ($entity->getEmail() ?? '') : ($model->get('email') ?? ''),
                'subject' => method_exists($entity, 'getSubject') ? ($entity->getSubject() ?? '') : ($model->get('subject') ?? ''),
                'message' => method_exists($entity, 'getMessage') ? ($entity->getMessage() ?? '') : ($model->get('message') ?? ''),
            ]);
        }

        return $model;
    }

    /**
     * @return ClassMetadata<object>
     */
    private function getMetadata(): ClassMetadata
    {
        $em = $this->requireEntityManager();
        $class = $this->getEntityClass();
        /** @var ClassMetadata<object> $metadata */
        $metadata = $em->getClassMetadata($class);
        return $metadata;
    }

    private function requireEntityManager(): EntityManagerInterface
    {
        if ($this->entityManager === null || $this->entityClass === null) {
            throw new \RuntimeException('Doctrine EntityManager or entity class not configured for CRUD operations.');
        }

        return $this->entityManager;
    }

    /**
     * @return class-string<object>
     */
    private function getEntityClass(): string
    {
        if ($this->entityClass === null) {
            return ContactMessageEntity::class;
        }

        /** @var class-string<object> $class */
        $class = $this->entityClass;
        return $class;
    }
}

