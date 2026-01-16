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

                // Spam protection configuration
                ->arrayNode('spam_protection')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('level')
                            ->info('Protection level: 1=honeypot+rate limit, 2=+email verification, 3=+captcha')
                            ->min(1)
                            ->max(3)
                            ->defaultValue(1)
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
                            ->info('Verification token TTL (e.g., "1 hour", "30 minutes")')
                            ->defaultValue('1 hour')
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
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
