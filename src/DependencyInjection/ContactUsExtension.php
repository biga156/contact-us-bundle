<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ContactUsExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Prepend configuration to ensure English translations are always available as fallback
     */
    public function prepend(ContainerBuilder $container): void
    {
        // Get existing framework configuration
        $configs = $container->getExtensionConfig('framework');
        
        // Check if 'en' is already in fallbacks
        $fallbacks = [];
        foreach ($configs as $config) {
            if (isset($config['translator']['fallbacks'])) {
                $fallbacks = array_merge($fallbacks, (array) $config['translator']['fallbacks']);
            }
        }
        
        // Add 'en' to fallbacks if not present (bundle provides English translations by default)
        if (!in_array('en', $fallbacks, true)) {
            $container->prependExtensionConfig('framework', [
                'translator' => [
                    'fallbacks' => array_merge($fallbacks, ['en']),
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Register configuration as parameters
        $container->setParameter('contact_us.recipients', $config['recipients']);
        $container->setParameter('contact_us.subject_prefix', $config['subject_prefix']);
        $container->setParameter('contact_us.storage', $config['storage']);
        $container->setParameter('contact_us.entity_class', $config['entity_class'] ?? \Caeligo\ContactUsBundle\Entity\ContactMessageEntity::class);
        $container->setParameter('contact_us.spam_protection', $config['spam_protection']);
        $container->setParameter('contact_us.fields', $config['fields']);
        $container->setParameter('contact_us.email_verification', $config['email_verification']);
        $container->setParameter('contact_us.mailer', $config['mailer']);
        $container->setParameter('contact_us.mailer.from_email', $config['mailer']['from_email'] ?? ($_ENV['CONTACT_EMAIL'] ?? null));
        $container->setParameter('contact_us.mailer.from_name', $config['mailer']['from_name']);
        $container->setParameter('contact_us.mailer.send_copy_to_sender', $config['mailer']['send_copy_to_sender'] ?? false);
        $container->setParameter('contact_us.templates', $config['templates']);
        $container->setParameter('contact_us.design', $config['design']);
        $container->setParameter('contact_us.translation', $config['translation']);

        // Load services
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function getAlias(): string
    {
        return 'contact_us';
    }
}
