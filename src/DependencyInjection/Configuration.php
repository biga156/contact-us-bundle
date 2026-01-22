<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('contact_us');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                // Recipients configuration
                ->arrayNode('recipients')
                    ->info('Email addresses to receive contact form submissions')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()

                // Subject prefix
                ->scalarNode('subject_prefix')
                    ->info('Prefix for email subjects')
                    ->defaultValue('[Contact Form]')
                ->end()

                // Storage mode
                ->enumNode('storage')
                    ->info('Storage mode: email (email only), database (database only), or both')
                    ->values(['email', 'database', 'both'])
                    ->defaultValue('email')
                ->end()

                // Entity class for custom entity mapping
                ->scalarNode('entity_class')
                    ->info('Custom entity class to use for persistence (defaults to Caeligo\ContactUsBundle\Entity\ContactMessageEntity)')
                    ->defaultNull()
                ->end()

                // CRUD route prefix (only used with bundle entity + database storage)
                ->scalarNode('crud_route_prefix')
                    ->info('Base URL path for admin CRUD routes (only used when storage=database|both and using bundle entity)')
                    ->defaultValue('/admin/contact')
                ->end()

                // Spam protection configuration
                ->arrayNode('spam_protection')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('features')
                            ->info('Enabled spam protection features: 1=honeypot+rate limit, 2=email verification, 3=captcha')
                            ->integerPrototype()
                                ->min(1)
                                ->max(3)
                            ->end()
                            ->defaultValue([1])
                        ->end()
                        // Legacy support: also accept 'level' for backward compatibility
                        ->integerNode('level')
                            ->info('[DEPRECATED] Use "features" instead. Protection level: 1=honeypot+rate limit, 2=+email verification, 3=+captcha')
                            ->min(1)
                            ->max(3)
                            ->defaultNull()
                        ->end()
                        ->arrayNode('rate_limit')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('limit')
                                    ->info('Maximum submissions allowed per interval')
                                    ->defaultValue(3)
                                ->end()
                                ->scalarNode('interval')
                                    ->info('Time interval (e.g., "15 minutes", "1 hour")')
                                    ->defaultValue('15 minutes')
                                ->end()
                            ->end()
                        ->end()
                        ->integerNode('min_submit_time')
                            ->info('Minimum time in seconds between form load and submit')
                            ->defaultValue(3)
                        ->end()
                        ->arrayNode('captcha')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('provider')
                                    ->info('Captcha provider: none, turnstile, hcaptcha, friendly, recaptcha')
                                    ->values(['none', 'turnstile', 'hcaptcha', 'friendly', 'recaptcha'])
                                    ->defaultValue('none')
                                ->end()
                                ->scalarNode('site_key')
                                    ->info('Site/public key for the captcha provider')
                                    ->defaultNull()
                                ->end()
                                ->scalarNode('secret_key')
                                    ->info('Secret key for the captcha provider')
                                    ->defaultNull()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                // Form fields configuration
                ->arrayNode('fields')
                    ->info('Custom form fields configuration')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type')
                                ->info('Field type (text, email, textarea, tel, url, etc.)')
                                ->values(['text', 'email', 'textarea', 'tel', 'url', 'number', 'choice'])
                                ->defaultValue('text')
                            ->end()
                            ->booleanNode('required')
                                ->info('Whether the field is required')
                                ->defaultFalse()
                            ->end()
                            ->scalarNode('label')
                                ->info('Field label (translation key)')
                                ->defaultNull()
                            ->end()
                            ->variableNode('options')
                                ->info('Additional field options (e.g., choices for choice type)')
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('constraints')
                                ->info('Validation constraints (Symfony validator syntax)')
                                ->variablePrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([
                        'name' => [
                            'type' => 'text',
                            'required' => true,
                            'label' => 'contact.field.name',
                            'constraints' => [
                                ['NotBlank' => []],
                                ['Length' => ['max' => 100]],
                            ],
                        ],
                        'email' => [
                            'type' => 'email',
                            'required' => true,
                            'label' => 'contact.field.email',
                            'constraints' => [
                                ['NotBlank' => []],
                                ['Email' => []],
                            ],
                        ],
                        'subject' => [
                            'type' => 'text',
                            'required' => true,
                            'label' => 'contact.field.subject',
                            'constraints' => [
                                ['NotBlank' => []],
                                ['Length' => ['max' => 200]],
                            ],
                        ],
                        'message' => [
                            'type' => 'textarea',
                            'required' => true,
                            'label' => 'contact.field.message',
                            'constraints' => [
                                ['NotBlank' => []],
                                ['Length' => ['min' => 10, 'max' => 5000]],
                            ],
                        ],
                    ])
                ->end()

                // API configuration
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Enable REST API endpoints')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('route_prefix')
                            ->info('API route prefix')
                            ->defaultValue('/api/contact')
                        ->end()
                    ->end()
                ->end()

                // Email verification (L2)
                ->arrayNode('email_verification')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Require email verification before processing')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('token_ttl')
                            ->info('Verification token TTL (e.g., "24 hours", "12 hours")')
                            ->defaultValue('24 hours')
                        ->end()
                    ->end()
                ->end()

                // Mailer configuration
                ->arrayNode('mailer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('from_email')
                            ->info('From email address for notifications')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('from_name')
                            ->info('From name for notifications')
                            ->defaultValue('Contact Form')
                        ->end()
                        ->booleanNode('send_copy_to_sender')
                            ->info('Also send the notification email to the sender address')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()

                // Template configuration
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base')
                            ->info('Base template to extend (use null for standalone page)')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('form')
                            ->info('Form template path')
                            ->defaultValue('@ContactUs/contact/form.html.twig')
                        ->end()
                        ->scalarNode('email_html')
                            ->info('HTML email template path')
                            ->defaultValue('@ContactUs/email/contact_notification.html.twig')
                        ->end()
                        ->scalarNode('email_text')
                            ->info('Text email template path')
                            ->defaultValue('@ContactUs/email/contact_notification.txt.twig')
                        ->end()
                    ->end()
                ->end()

                // Design/Assets configuration
                ->arrayNode('design')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('load_default_styles')
                            ->info('Load default CSS (set false if using custom styles)')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('load_stimulus_controller')
                            ->info('Load Stimulus controller (set false if handling manually)')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('form_theme')
                            ->info('Symfony form theme to use')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()

                // Translation configuration
                ->arrayNode('translation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('enabled')
                            ->info('Enable translations: auto (auto-detect), true (force), false (disable)')
                            ->values(['auto', 'true', 'false'])
                            ->defaultValue('auto')
                        ->end()
                        ->scalarNode('domain')
                            ->info('Translation domain')
                            ->defaultValue('contact_us')
                        ->end()
                        ->scalarNode('fallback_locale')
                            ->info('Fallback locale if translation key not found')
                            ->defaultValue('en')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
