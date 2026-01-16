<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ContactUsExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Register configuration as parameters
        $container->setParameter('contact_us.recipients', $config['recipients']);
        $container->setParameter('contact_us.subject_prefix', $config['subject_prefix']);
        $container->setParameter('contact_us.storage', $config['storage']);
        $container->setParameter('contact_us.entity_class', $config['entity_class']);
        $container->setParameter('contact_us.spam_protection', $config['spam_protection']);
        $container->setParameter('contact_us.fields', $config['fields']);
        $container->setParameter('contact_us.api', $config['api']);
        $container->setParameter('contact_us.email_verification', $config['email_verification']);
        $container->setParameter('contact_us.mailer', $config['mailer']);
        $container->setParameter('contact_us.templates', $config['templates']);
        $container->setParameter('contact_us.design', $config['design']);

        // Load services
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function getAlias(): string
    {
        return 'contact_us';
    }
}
