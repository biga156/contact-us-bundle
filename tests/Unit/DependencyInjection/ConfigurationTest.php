<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Tests\Unit\DependencyInjection;

use Caeligo\ContactUsBundle\DependencyInjection\Configuration;
use Caeligo\ContactUsBundle\Entity\ContactMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            []
        );

        $this->assertEquals(ContactMessage::class, $config['entity_class']);
        $this->assertTrue($config['email']['enabled']);
        $this->assertTrue($config['rate_limiting']['enabled']);
        $this->assertEquals(5, $config['rate_limiting']['max_attempts']);
        $this->assertEquals(3600, $config['rate_limiting']['time_window']);
    }

    public function testCustomEntityClass(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'entity_class' => 'App\Entity\CustomMessage',
                ],
            ]
        );

        $this->assertEquals('App\Entity\CustomMessage', $config['entity_class']);
    }

    public function testEmailConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'email' => [
                        'enabled' => true,
                        'from' => 'contact@example.com',
                        'to' => 'admin@example.com',
                        'subject_prefix' => '[Web Contact]',
                    ],
                ],
            ]
        );

        $this->assertTrue($config['email']['enabled']);
        $this->assertEquals('contact@example.com', $config['email']['from']);
        $this->assertEquals('admin@example.com', $config['email']['to']);
        $this->assertEquals('[Web Contact]', $config['email']['subject_prefix']);
    }

    public function testRateLimitingConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'rate_limiting' => [
                        'enabled' => true,
                        'max_attempts' => 10,
                        'time_window' => 7200,
                    ],
                ],
            ]
        );

        $this->assertTrue($config['rate_limiting']['enabled']);
        $this->assertEquals(10, $config['rate_limiting']['max_attempts']);
        $this->assertEquals(7200, $config['rate_limiting']['time_window']);
    }

    public function testTemplateConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'templates' => [
                        'base' => 'custom/base.html.twig',
                        'form' => 'custom/form.html.twig',
                        'email' => 'custom/email.html.twig',
                    ],
                ],
            ]
        );

        $this->assertEquals('custom/base.html.twig', $config['templates']['base']);
        $this->assertEquals('custom/form.html.twig', $config['templates']['form']);
        $this->assertEquals('custom/email.html.twig', $config['templates']['email']);
    }

    public function testDesignConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'design' => [
                        'load_default_styles' => false,
                        'load_stimulus_controller' => false,
                        'form_theme' => 'bootstrap_5_layout.html.twig',
                    ],
                ],
            ]
        );

        $this->assertFalse($config['design']['load_default_styles']);
        $this->assertFalse($config['design']['load_stimulus_controller']);
        $this->assertEquals('bootstrap_5_layout.html.twig', $config['design']['form_theme']);
    }

    public function testTranslationConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'translation' => [
                        'enabled' => 'true',
                        'domain' => 'messages',
                        'fallback_locale' => 'fr',
                    ],
                ],
            ]
        );

        $this->assertEquals('true', $config['translation']['enabled']);
        $this->assertEquals('messages', $config['translation']['domain']);
        $this->assertEquals('fr', $config['translation']['fallback_locale']);
    }

    public function testTranslationAutoDetect(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'translation' => [
                        'enabled' => 'auto',
                    ],
                ],
            ]
        );

        $this->assertEquals('auto', $config['translation']['enabled']);
    }

    public function testSuccessRedirectConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'success_redirect_route' => 'app_homepage',
                ],
            ]
        );

        $this->assertEquals('app_homepage', $config['success_redirect_route']);
    }

    public function testSpamProtectionConfiguration(): void
    {
        $config = $this->processor->processConfiguration(
            $this->configuration,
            [
                'contact_us' => [
                    'spam_protection' => [
                        'timing_check' => [
                            'enabled' => true,
                            'min_seconds' => 5,
                        ],
                        'honeypot' => [
                            'enabled' => true,
                        ],
                    ],
                ],
            ]
        );

        $this->assertTrue($config['spam_protection']['timing_check']['enabled']);
        $this->assertEquals(5, $config['spam_protection']['timing_check']['min_seconds']);
        $this->assertTrue($config['spam_protection']['honeypot']['enabled']);
    }

    public function testCompleteConfiguration(): void
    {
        $fullConfig = [
            'contact_us' => [
                'entity_class' => 'App\Entity\Contact',
                'email' => [
                    'enabled' => true,
                    'from' => 'no-reply@example.com',
                    'to' => 'support@example.com',
                    'subject_prefix' => '[Support]',
                ],
                'rate_limiting' => [
                    'enabled' => true,
                    'max_attempts' => 3,
                    'time_window' => 1800,
                ],
                'templates' => [
                    'base' => 'base.html.twig',
                    'form' => 'contact_form.html.twig',
                    'email' => 'email.html.twig',
                ],
                'design' => [
                    'load_default_styles' => true,
                    'load_stimulus_controller' => true,
                    'form_theme' => null,
                ],
                'translation' => [
                    'enabled' => 'auto',
                    'domain' => 'contact_us',
                    'fallback_locale' => 'en',
                ],
                'success_redirect_route' => 'app_contact_success',
                'spam_protection' => [
                    'timing_check' => [
                        'enabled' => true,
                        'min_seconds' => 4,
                    ],
                    'honeypot' => [
                        'enabled' => true,
                    ],
                ],
            ],
        ];

        $config = $this->processor->processConfiguration(
            $this->configuration,
            $fullConfig
        );

        $this->assertArrayHasKey('entity_class', $config);
        $this->assertArrayHasKey('email', $config);
        $this->assertArrayHasKey('rate_limiting', $config);
        $this->assertArrayHasKey('templates', $config);
        $this->assertArrayHasKey('design', $config);
        $this->assertArrayHasKey('translation', $config);
        $this->assertArrayHasKey('success_redirect_route', $config);
        $this->assertArrayHasKey('spam_protection', $config);
    }
}
