<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Integration;

use Caeligo\ContactUsBundle\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;

class ContactFormIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testCompleteContactFormFlow(): void
    {
        $storage = static::getContainer()->get('contact_us.storage');
        $emailNotifier = static::getContainer()->get('contact_us.email_notifier');

        // Create a contact message
        $message = new ContactMessage();
        $message->setName('Integration Test User');
        $message->setEmail('integration@example.com');
        $message->setSubject('Integration Test Subject');
        $message->setMessage('This is an integration test message to verify the complete flow works correctly.');
        $message->setIpAddress('127.0.0.1');
        $message->setUserAgent('PHPUnit Test');

        // Save to database
        $storage->save($message);

        // Verify it was persisted
        $this->assertNotNull($message->getId());
        
        // Find it in database
        $repository = $this->entityManager->getRepository(ContactMessage::class);
        $savedMessage = $repository->findOneBy(['email' => 'integration@example.com']);

        $this->assertNotNull($savedMessage);
        $this->assertEquals('Integration Test User', $savedMessage->getName());
        $this->assertEquals('Integration Test Subject', $savedMessage->getSubject());

        // Cleanup
        $this->entityManager->remove($savedMessage);
        $this->entityManager->flush();
    }

    public function testEmailNotificationIsSent(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $emailNotifier = static::getContainer()->get('contact_us.email_notifier');
        
        $message = new ContactMessage();
        $message->setName('Email Test User');
        $message->setEmail('emailtest@example.com');
        $message->setSubject('Email Test');
        $message->setMessage('Testing email notification functionality.');

        // This should trigger email sending if enabled
        $emailNotifier->sendNotification($message);
    }

    public function testFormTypeIntegration(): void
    {
        $formFactory = static::getContainer()->get('form.factory');
        $form = $formFactory->create(\Caeligo\ContactUsBundle\Form\ContactFormType::class);

        $this->assertNotNull($form);
        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('subject'));
        $this->assertTrue($form->has('message'));
        $this->assertTrue($form->has('_form_token_time'));
    }

    public function testValidatorServicesAreRegistered(): void
    {
        $validator = static::getContainer()->get('validator');
        
        $message = new ContactMessage();
        $message->setName('Test');
        $message->setEmail('invalid-email');
        $message->setMessage('Too short');

        $errors = $validator->validate($message);
        
        $this->assertGreaterThan(0, count($errors));
    }

    public function testTwigExtensionIsRegistered(): void
    {
        $twig = static::getContainer()->get('twig');
        
        $this->assertTrue($twig->hasExtension(\Caeligo\ContactUsBundle\Twig\ContactUsExtension::class));
        
        $extension = $twig->getExtension(\Caeligo\ContactUsBundle\Twig\ContactUsExtension::class);
        $this->assertNotNull($extension);
    }

    public function testConfigurationParametersAreLoaded(): void
    {
        $container = static::getContainer();

        $this->assertTrue($container->hasParameter('contact_us.entity_class'));
        $this->assertTrue($container->hasParameter('contact_us.email'));
        $this->assertTrue($container->hasParameter('contact_us.rate_limiting'));
        $this->assertTrue($container->hasParameter('contact_us.templates'));
        $this->assertTrue($container->hasParameter('contact_us.design'));
        $this->assertTrue($container->hasParameter('contact_us.translation'));
    }

    public function testCustomEntityClassConfiguration(): void
    {
        $entityClass = static::getContainer()->getParameter('contact_us.entity_class');
        
        $this->assertNotEmpty($entityClass);
        $this->assertTrue(class_exists($entityClass));
    }

    public function testStorageServiceWorks(): void
    {
        $storage = static::getContainer()->get('contact_us.storage');
        
        $message = new ContactMessage();
        $message->setName('Storage Test');
        $message->setEmail('storage@example.com');
        $message->setSubject('Storage Test');
        $message->setMessage('Testing storage service integration.');

        // Should not throw exception
        $storage->save($message);
        
        $this->assertNotNull($message->getId());

        // Cleanup
        $this->entityManager->remove($message);
        $this->entityManager->flush();
    }

    public function testRateLimitingConfiguration(): void
    {
        $rateLimitConfig = static::getContainer()->getParameter('contact_us.rate_limiting');
        
        $this->assertIsArray($rateLimitConfig);
        $this->assertArrayHasKey('enabled', $rateLimitConfig);
        $this->assertArrayHasKey('max_attempts', $rateLimitConfig);
        $this->assertArrayHasKey('time_window', $rateLimitConfig);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
