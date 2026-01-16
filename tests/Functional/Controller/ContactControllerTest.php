<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Functional\Controller;

use Caeligo\ContactUsBundle\Entity\ContactMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ContactControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testContactFormPageLoads(): void
    {
        $this->client->request('GET', '/contact');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="contact"]');
        $this->assertSelectorExists('input[name="contact[name]"]');
        $this->assertSelectorExists('input[name="contact[email]"]');
        $this->assertSelectorExists('input[name="contact[subject]"]');
        $this->assertSelectorTextareaExists('textarea[name="contact[message]"]');
    }

    public function testContactFormSubmissionWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        
        // Wait sufficient time for timing validator
        sleep(4);

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => 'Test User',
            'contact[email]' => 'test@example.com',
            'contact[subject]' => 'Test Subject',
            'contact[message]' => 'This is a test message with sufficient length to pass validation.',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-success');
    }

    public function testContactFormValidationErrors(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => '', // Empty name - should fail
            'contact[email]' => 'invalid-email', // Invalid email
            'contact[subject]' => 'Test',
            'contact[message]' => 'Short', // Too short message
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSelectorExists('.form-error, .invalid-feedback');
    }

    public function testContactFormRequiredFields(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => '',
            'contact[email]' => '',
            'contact[subject]' => '',
            'contact[message]' => '',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        
        // Should have multiple validation errors
        $errorCount = $crawler->filter('.form-error, .invalid-feedback')->count();
        $this->assertGreaterThan(0, $errorCount);
    }

    public function testEmailValidation(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => 'Test User',
            'contact[email]' => 'not-an-email',
            'contact[subject]' => 'Test',
            'contact[message]' => 'This is a valid message with enough content.',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSelectorTextContains('.form-error, .invalid-feedback', 'email');
    }

    public function testTimingValidatorPreventsQuickSubmission(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        // Submit immediately without waiting
        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => 'Test User',
            'contact[email]' => 'test@example.com',
            'contact[subject]' => 'Test',
            'contact[message]' => 'This is a test message with sufficient length.',
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testHoneypotFieldDetectsSpam(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        
        sleep(4); // Wait for timing validator

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => 'Test User',
            'contact[email]' => 'test@example.com',
            'contact[subject]' => 'Test',
            'contact[message]' => 'This is a test message.',
            'contact[website]' => 'http://spam.com', // Honeypot field filled
        ]);

        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testMultiLocaleRoutes(): void
    {
        $locales = ['en', 'fr', 'hu'];

        foreach ($locales as $locale) {
            $this->client->request('GET', "/{$locale}/contact");
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('form[name="contact"]');
        }
    }

    public function testCsrfTokenProtection(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        sleep(4);

        // Try to submit with invalid CSRF token
        $this->client->request('POST', '/contact', [
            'contact' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'subject' => 'Test',
                'message' => 'Test message with sufficient length.',
                '_token' => 'invalid_token',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFormPersistsDataOnValidationError(): void
    {
        $crawler = $this->client->request('GET', '/contact');

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => 'Remembered Name',
            'contact[email]' => 'invalid-email',
            'contact[subject]' => 'Remembered Subject',
            'contact[message]' => 'This message should be remembered.',
        ]);

        $crawler = $this->client->submit($form);

        // Check that valid data is still present in form
        $this->assertEquals('Remembered Name', $crawler->filter('input[name="contact[name]"]')->attr('value'));
        $this->assertEquals('Remembered Subject', $crawler->filter('input[name="contact[subject]"]')->attr('value'));
        $this->assertStringContainsString('This message should be remembered.', 
            $crawler->filter('textarea[name="contact[message]"]')->text());
    }

    public function testSuccessMessageAfterSubmission(): void
    {
        $crawler = $this->client->request('GET', '/contact');
        
        sleep(4);

        $form = $crawler->selectButton('submit')->form([
            'contact[name]' => 'Success Test User',
            'contact[email]' => 'success@example.com',
            'contact[subject]' => 'Success Test',
            'contact[message]' => 'This is a successful test message with sufficient length for validation.',
        ]);

        $this->client->submit($form);
        $this->client->followRedirect();

        $this->assertSelectorExists('.alert-success, .flash-success');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }
}
