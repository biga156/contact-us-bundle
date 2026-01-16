<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\DependencyInjection;

use Caeligo\ContactUsBundle\DependencyInjection\ContactUsExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ContactUsExtensionTest extends TestCase
{
    private ContactUsExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new ContactUsExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadDefaultConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasParameter('contact_us.entity_class'));
        $this->assertTrue($this->container->hasParameter('contact_us.email'));
        $this->assertTrue($this->container->hasParameter('contact_us.rate_limiting'));
        $this->assertTrue($this->container->hasParameter('contact_us.templates'));
        $this->assertTrue($this->container->hasParameter('contact_us.design'));
        $this->assertTrue($this->container->hasParameter('contact_us.translation'));
    }

    public function testLoadWithCustomEntityClass(): void
    {
        $config = [
            'contact_us' => [
                'entity_class' => 'App\Entity\CustomContact',
            ],
        ];

        $this->extension->load($config, $this->container);

        $this->assertEquals(
            'App\Entity\CustomContact',
            $this->container->getParameter('contact_us.entity_class')
        );
    }

    public function testLoadEmailConfiguration(): void
    {
        $config = [
            'contact_us' => [
                'email' => [
                    'enabled' => true,
                    'from' => 'test@example.com',
                    'to' => 'admin@example.com',
                    'subject_prefix' => '[TEST]',
                ],
            ],
        ];

        $this->extension->load($config, $this->container);

        $emailConfig = $this->container->getParameter('contact_us.email');
        $this->assertTrue($emailConfig['enabled']);
        $this->assertEquals('test@example.com', $emailConfig['from']);
        $this->assertEquals('admin@example.com', $emailConfig['to']);
        $this->assertEquals('[TEST]', $emailConfig['subject_prefix']);
    }

    public function testLoadTemplatesConfiguration(): void
    {
        $config = [
            'contact_us' => [
                'templates' => [
                    'base' => 'custom_base.html.twig',
                    'form' => 'custom_form.html.twig',
                    'email' => 'custom_email.html.twig',
                ],
            ],
        ];

        $this->extension->load($config, $this->container);

        $templatesConfig = $this->container->getParameter('contact_us.templates');
        $this->assertEquals('custom_base.html.twig', $templatesConfig['base']);
        $this->assertEquals('custom_form.html.twig', $templatesConfig['form']);
        $this->assertEquals('custom_email.html.twig', $templatesConfig['email']);
    }

    public function testLoadDesignConfiguration(): void
    {
        $config = [
            'contact_us' => [
                'design' => [
                    'load_default_styles' => false,
                    'load_stimulus_controller' => false,
                    'form_theme' => 'bootstrap_5_layout.html.twig',
                ],
            ],
        ];

        $this->extension->load($config, $this->container);

        $designConfig = $this->container->getParameter('contact_us.design');
        $this->assertFalse($designConfig['load_default_styles']);
        $this->assertFalse($designConfig['load_stimulus_controller']);
        $this->assertEquals('bootstrap_5_layout.html.twig', $designConfig['form_theme']);
    }

    public function testLoadTranslationConfiguration(): void
    {
        $config = [
            'contact_us' => [
                'translation' => [
                    'enabled' => 'true',
                    'domain' => 'messages',
                    'fallback_locale' => 'fr',
                ],
            ],
        ];

        $this->extension->load($config, $this->container);

        $translationConfig = $this->container->getParameter('contact_us.translation');
        $this->assertEquals('true', $translationConfig['enabled']);
        $this->assertEquals('messages', $translationConfig['domain']);
        $this->assertEquals('fr', $translationConfig['fallback_locale']);
    }

    public function testServicesAreRegistered(): void
    {
        $this->extension->load([], $this->container);

        // Check that bundle services are registered
        $this->assertTrue($this->container->has('contact_us.controller'));
        $this->assertTrue($this->container->has('contact_us.form.type'));
        $this->assertTrue($this->container->has('contact_us.storage'));
        $this->assertTrue($this->container->has('contact_us.email_notifier'));
    }

    public function testExtensionAlias(): void
    {
        $this->assertEquals('contact_us', $this->extension->getAlias());
    }

    public function testRateLimitingConfiguration(): void
    {
        $config = [
            'contact_us' => [
                'rate_limiting' => [
                    'enabled' => false,
                    'max_attempts' => 20,
                    'time_window' => 3600,
                ],
            ],
        ];

        $this->extension->load($config, $this->container);

        $rateLimitConfig = $this->container->getParameter('contact_us.rate_limiting');
        $this->assertFalse($rateLimitConfig['enabled']);
        $this->assertEquals(20, $rateLimitConfig['max_attempts']);
        $this->assertEquals(3600, $rateLimitConfig['time_window']);
    }

    public function testSpamProtectionConfiguration(): void
    {
        $config = [
            'contact_us' => [
                'spam_protection' => [
                    'timing_check' => [
                        'enabled' => true,
                        'min_seconds' => 10,
                    ],
                    'honeypot' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ];

        $this->extension->load($config, $this->container);

        $spamConfig = $this->container->getParameter('contact_us.spam_protection');
        $this->assertTrue($spamConfig['timing_check']['enabled']);
        $this->assertEquals(10, $spamConfig['timing_check']['min_seconds']);
        $this->assertFalse($spamConfig['honeypot']['enabled']);
    }
}
