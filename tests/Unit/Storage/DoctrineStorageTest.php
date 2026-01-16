<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\Storage;

use Caeligo\ContactUsBundle\Entity\ContactMessage;
use Caeligo\ContactUsBundle\Storage\DoctrineStorage;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class DoctrineStorageTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineStorage $storage;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->storage = new DoctrineStorage($this->entityManager, ContactMessage::class);
    }

    public function testSaveCallsPersistAndFlush(): void
    {
        $message = new ContactMessage();
        $message->setName('John Doe');
        $message->setEmail('john@example.com');
        $message->setSubject('Test');
        $message->setMessage('Test message');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($message);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->storage->save($message);
    }

    public function testSaveWithCustomEntityClass(): void
    {
        $customEntityClass = 'App\Entity\CustomContactMessage';
        $storage = new DoctrineStorage($this->entityManager, $customEntityClass);

        $message = new ContactMessage();
        $message->setName('Jane Doe');
        $message->setEmail('jane@example.com');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($message);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $storage->save($message);
    }

    public function testSaveHandlesException(): void
    {
        $message = new ContactMessage();
        $message->setName('John Doe');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($message);

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->storage->save($message);
    }

    public function testSaveWithCompleteData(): void
    {
        $message = new ContactMessage();
        $message->setName('John Doe');
        $message->setEmail('john@example.com');
        $message->setSubject('Important Subject');
        $message->setMessage('This is a detailed message with important content.');
        $message->setIpAddress('192.168.1.1');
        $message->setUserAgent('Mozilla/5.0');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($arg) use ($message) {
                return $arg instanceof ContactMessage
                    && $arg->getName() === 'John Doe'
                    && $arg->getEmail() === 'john@example.com'
                    && $arg->getSubject() === 'Important Subject'
                    && $arg->getMessage() === 'This is a detailed message with important content.'
                    && $arg->getIpAddress() === '192.168.1.1'
                    && $arg->getUserAgent() === 'Mozilla/5.0';
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->storage->save($message);
    }
}
