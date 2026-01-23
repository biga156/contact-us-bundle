<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Caeligo\ContactUsBundle\CacheWarmer\AutoSyncCacheWarmer;
use Caeligo\ContactUsBundle\Command\SetupCommand;
use Caeligo\ContactUsBundle\Controller\Admin\DefaultContactCrudController;
use Caeligo\ContactUsBundle\Controller\ContactController;
use Caeligo\ContactUsBundle\Form\ContactFormType;
use Caeligo\ContactUsBundle\Service\ContactMailer;
use Caeligo\ContactUsBundle\Service\ContactSubmissionService;
use Caeligo\ContactUsBundle\Service\Crud\ContactCrudManager;
use Caeligo\ContactUsBundle\Service\Crud\CrudManagerInterface;
use Caeligo\ContactUsBundle\SpamProtection\ContactRateLimiter;
use Caeligo\ContactUsBundle\SpamProtection\HoneypotValidator;
use Caeligo\ContactUsBundle\SpamProtection\NullCaptchaValidator;
use Caeligo\ContactUsBundle\SpamProtection\TimingValidator;
use Caeligo\ContactUsBundle\Storage\DoctrineStorage;
use Caeligo\ContactUsBundle\Storage\NullStorage;
use Caeligo\ContactUsBundle\Storage\StorageInterface;
use Caeligo\ContactUsBundle\Twig\ContactUsExtension;

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
        ->arg('$fromEmail', param('contact_us.mailer.from_email'))
        ->arg('$fromName', param('contact_us.mailer.from_name'))
        ->arg('$enableAutoReply', false)
        ->arg('$autoReplyFrom', param('contact_us.mailer.from_email'))
        ->arg('$sendCopyToSender', param('contact_us.mailer.send_copy_to_sender'))
        ->arg('$urlGenerator', service('router')->nullOnInvalid())
        ->arg('$translator', service('translator')->nullOnInvalid())
        ->arg('$defaultLocale', '%kernel.default_locale%');

    // CRUD Manager (bundle default)
    $services->set(ContactCrudManager::class)
        ->arg('$entityManager', service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->arg('$entityClass', param('contact_us.entity_class'));

    $services->alias(CrudManagerInterface::class, ContactCrudManager::class)
        ->public();

    // Submission Service
    $services->set(ContactSubmissionService::class)
        ->arg('$storage', service(StorageInterface::class))
        ->arg('$mailer', service(ContactMailer::class))
        ->arg('$honeypotValidator', service(HoneypotValidator::class))
        ->arg('$timingValidator', service(TimingValidator::class))
        ->arg('$rateLimiter', service(ContactRateLimiter::class))
        ->arg('$eventDispatcher', service('event_dispatcher'))
        ->arg('$storageMode', param('contact_us.storage'))
        ->arg('$emailVerificationConfig', param('contact_us.email_verification'));

    // Controller
    $services->set(ContactController::class)
        ->arg('$submissionService', service(ContactSubmissionService::class))
        ->arg('$fieldsConfig', param('contact_us.fields'))
        ->arg('$formFactory', service('form.factory'))
        ->arg('$urlGenerator', service('router'))
        ->tag('controller.service_arguments');

    $services->set(DefaultContactCrudController::class)
        ->arg('$manager', service(CrudManagerInterface::class))
        ->arg('$dispatcher', service('event_dispatcher'))
        ->tag('controller.service_arguments');

    // Commands
    $services->set(SetupCommand::class)
        ->arg('$entityManager', service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->arg('$projectDir', param('kernel.project_dir'))
        ->tag('console.command');

    // Twig Extension
    $services->set(ContactUsExtension::class)
        ->arg('$templates', param('contact_us.templates'))
        ->arg('$design', param('contact_us.design'))
        ->arg('$translation', param('contact_us.translation'))
        ->arg('$translator', service('translator')->nullOnInvalid())
        ->tag('twig.extension');

    // Dev-mode: Auto-sync cache warmer (runs during cache:clear warmup phase)
    $services->set(AutoSyncCacheWarmer::class)
        ->arg('$projectDir', param('kernel.project_dir'))
        ->arg('$isDebug', param('kernel.debug'))
        ->arg('$autoSyncEnabled', param('contact_us.dev.auto_sync'))
        ->arg('$entityManager', service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->tag('kernel.cache_warmer', ['priority' => -100]);
};
