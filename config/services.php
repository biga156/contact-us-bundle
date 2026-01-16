<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Caeligo\ContactUsBundle\Command\SetupCommand;
use Caeligo\ContactUsBundle\Controller\ContactController;
use Caeligo\ContactUsBundle\Form\ContactFormType;
use Caeligo\ContactUsBundle\Service\ContactMailer;
use Caeligo\ContactUsBundle\Service\ContactSubmissionService;
use Caeligo\ContactUsBundle\SpamProtection\ContactRateLimiter;
use Caeligo\ContactUsBundle\SpamProtection\HoneypotValidator;
use Caeligo\ContactUsBundle\SpamProtection\NullCaptchaValidator;
use Caeligo\ContactUsBundle\SpamProtection\TimingValidator;
use Caeligo\ContactUsBundle\Storage\DoctrineStorage;
use Caeligo\ContactUsBundle\Storage\NullStorage;
use Caeligo\ContactUsBundle\Storage\StorageInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // Form Type
    $services->set(ContactFormType::class)
        ->arg('$fieldsConfig', param('contact_us.fields'))
        ->tag('form.type');

    // Storage
    $services->set(NullStorage::class);
    
    $services->set(DoctrineStorage::class)
        ->arg('$entityManager', service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->arg('$entityClass', param('contact_us.entity_class'));

    $services->alias(StorageInterface::class, DoctrineStorage::class)
        ->public();

    // Spam Protection
    $services->set(HoneypotValidator::class);

    $services->set(TimingValidator::class)
        ->arg('$minSubmitTime', 3);

    $services->set(ContactRateLimiter::class)
        ->arg('$requestStack', service('request_stack'))
        ->arg('$limit', 3)
        ->arg('$interval', '15 minutes');

    $services->set(NullCaptchaValidator::class);

    // Mailer
    $services->set(ContactMailer::class)
        ->arg('$mailer', service('mailer'))
        ->arg('$recipients', param('contact_us.recipients'))
        ->arg('$subjectPrefix', param('contact_us.subject_prefix'))
        ->arg('$fromEmail', null)
        ->arg('$fromName', 'Contact Form');

    // Submission Service
    $services->set(ContactSubmissionService::class)
        ->arg('$storage', service(StorageInterface::class))
        ->arg('$mailer', service(ContactMailer::class))
        ->arg('$honeypotValidator', service(HoneypotValidator::class))
        ->arg('$timingValidator', service(TimingValidator::class))
        ->arg('$rateLimiter', service(ContactRateLimiter::class))
        ->arg('$eventDispatcher', service('event_dispatcher'))
        ->arg('$storageMode', param('contact_us.storage'));

    // Controller
    $services->set(ContactController::class)
        ->arg('$submissionService', service(ContactSubmissionService::class))
        ->arg('$fieldsConfig', param('contact_us.fields'))
        ->arg('$formFactory', service('form.factory'))
        ->arg('$urlGenerator', service('router'))
        ->tag('controller.service_arguments');

    // Commands
    $services->set(SetupCommand::class)
        ->arg('$entityManager', service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->arg('$projectDir', param('kernel.project_dir'))
        ->tag('console.command');
};
