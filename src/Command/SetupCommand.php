<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'contact:setup',
    description: 'Interactive wizard to configure the ContactUs bundle',
)]
class SetupCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private ?EntityManagerInterface $entityManager = null,
        private string $projectDir = ''
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('ContactUs Bundle Setup Wizard');
        $this->io->text('This wizard will help you configure the contact form bundle.');

        // Non-interactive mode: accept defaults and skip questions
        if (!$input->isInteractive()) {
            $config = $this->buildNonInteractiveConfig();

            $this->saveConfiguration($config);
            $this->importRoutesToConfig();
            if ($config['storage'] === 'database' || $config['storage'] === 'both') {
                $this->importCrudRoutesToConfig();
            }
            $this->clearCacheAndCompileAssets();

            $this->io->success('ContactUs bundle configuration saved successfully (non-interactive).');
            $this->io->info('Configuration file: config/packages/contact_us.yaml');
            $this->io->info('Routes imported to: config/routes.yaml');
            $this->io->info('Cache cleared and assets compiled.');
            $this->io->section('Next Steps');
            $this->io->writeln('<comment>1. Generate and run migrations (bundle entity selected):</comment>');
            $this->io->writeln('   php bin/console make:migration');
            $this->io->writeln('   php bin/console doctrine:migrations:migrate');
            $this->io->writeln('');
            $this->io->writeln('<info>The contact form is now available at:</info>');
            $this->io->writeln('  • <fg=green>/contact</> (GET|POST) - Contact form page');
            $this->io->writeln('  • LiveComponent: {{ component(\'ContactUs\') }} in your templates');

            return Command::SUCCESS;
        }

        // Check for existing configuration
        $configFile = $this->projectDir . '/config/packages/contact_us.yaml';
        $previousConfig = null;

        if (file_exists($configFile)) {
            $this->io->section('Existing Configuration Found');
            $this->io->info('Configuration file already exists at: ' . $configFile);
            
            if (!$this->io->confirm('Would you like to update the existing configuration?', false)) {
                $this->io->info('Setup cancelled. Your existing configuration remains unchanged.');
                return Command::SUCCESS;
            }

            // Load previous config for comparison
            $previousConfig = Yaml::parseFile($configFile);
            $this->io->info('Previous configuration will be updated.');
        }

        $config = [];

        // Step 1: Storage mode (drives the rest of the flow)
        $config['storage'] = $this->askStorageMode();

        // Step 2: Recipients (only when email delivery is enabled)
        $config['recipients'] = in_array($config['storage'], ['email', 'both'], true)
            ? $this->askRecipients()
            : [];

        // Step 3: If database, ask about table/entity
        $usingBundleEntity = false;
        $switchingFromBundleEntity = false;
        if (in_array($config['storage'], ['database', 'both'], true)) {
            $entityConfig = $this->askEntityConfiguration();
            if ($entityConfig) {
                $config['entity'] = $entityConfig;
                $usingBundleEntity = !($entityConfig['use_existing'] ?? false);
                // Check if switching away from bundle entity to custom entity
                $switchingFromBundleEntity = ($previousConfig && ($previousConfig['entity']['use_existing'] ?? false) === false) && !$usingBundleEntity;
            }
        }

        // Step 4: Form fields (auto-detect or manual)
        $config['fields'] = $this->askFormFields($config['entity'] ?? null);

        // Step 5: Spam protection (base protections always enabled)
        $config['spam_protection'] = $this->askSpamProtection($config['storage']);

        // Step 6: Email verification (only in BOTH storage mode)
        $config['email_verification'] = $this->askEmailVerification($config['storage']);

        // Step 7: Mailer configuration (skip for database-only)
        $config['mailer'] = in_array($config['storage'], ['email', 'both'], true)
            ? $this->askMailerConfiguration($config['email_verification'])
            : $this->buildMailerDefaults();

        // Step 7: CRUD route prefix (only if database storage is enabled)
        $crudRoutePrefix = '/admin/contact';
        if (in_array($config['storage'], ['database', 'both'], true) && $usingBundleEntity) {
            $crudRoutePrefix = $this->askCrudRoutePrefix();
            $config['crud_route_prefix'] = $crudRoutePrefix;
        }

        // Step 8: Offer cleanup of bundle table when:
        // - Using email-only storage, OR
        // - Switching from bundle entity to custom entity (database/both mode)
        if ($config['storage'] === 'email' || $switchingFromBundleEntity) {
            $this->offerBundleTableDropForEmailStorage();
        }

        // Save configuration
        $this->saveConfiguration($config);
        $this->importRoutesToConfig();
        if ($usingBundleEntity && in_array($config['storage'], ['database', 'both'], true)) {
            $crudPrefix = $config['crud_route_prefix'] ?? '/admin/contact';
            $this->importCrudRoutesToConfig($crudPrefix);
        }
        $this->clearCacheAndCompileAssets();

        $this->io->success('ContactUs bundle configuration saved successfully!');
        $this->io->info('Configuration file: config/packages/contact_us.yaml');
        $this->io->info('Routes imported to: config/routes.yaml');
        $this->io->info('Cache cleared and assets compiled.');
        
        // Handle migrations if using bundle's own entity
        if ($usingBundleEntity) {
            if ($this->io->confirm('Would you like to generate and run migrations now?', true)) {
                $this->handleMigrations();
            } else {
                $this->io->section('Migration Required');
                $this->io->warning('You selected to use the bundle\'s own entity, which requires database migrations.');
                $this->io->listing([
                    'php bin/console make:migration',
                    'php bin/console doctrine:migrations:migrate',
                ]);
            }
        } elseif (isset($config['entity']) && ($config['entity']['use_existing'] ?? false)) {
            $this->io->section('No Migration Needed');
            $this->io->info('You selected an existing entity - no migration needed.');
        }
        
        $this->io->section('Next Steps');
        $this->io->writeln('<info>The contact form is now available at:</info>');
        $this->io->writeln('  • <fg=green>/contact</> (GET|POST) - Contact form page');
        $this->io->writeln('  • LiveComponent: {{ component(\'ContactUs\') }} in your templates');

        // List CRUD admin routes if database or both mode
        if (in_array($config['storage'], ['database', 'both'], true) && $usingBundleEntity) {
            $crudPrefix = $config['crud_route_prefix'] ?? '/admin/contact';
            $this->io->section('Admin CRUD Routes');
            $this->io->writeln('<info>The following admin routes are available for managing messages:</info>');
            $this->io->listing([
                "<fg=cyan>{$crudPrefix}</> (GET) - List all messages",
                "<fg=cyan>{$crudPrefix}/<id></> (GET) - View message details",
                "<fg=cyan>{$crudPrefix}/<id>/edit</> (GET|POST) - Edit message",
                "<fg=cyan>{$crudPrefix}/<id>/delete</> (POST) - Delete message",
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string>
     */
    private function askRecipients(): array
    {
        $this->io->section('Email Recipients');

        // Check for existing CONTACT_EMAIL env var
        $existingEmail = $_ENV['CONTACT_EMAIL'] ?? null;
        
        if ($existingEmail) {
            $useExisting = $this->io->confirm(
                sprintf('Use existing CONTACT_EMAIL (%s)?', $existingEmail),
                true
            );

            if ($useExisting) {
                return ["'%env(CONTACT_EMAIL)%'"];
            }
        }

        $recipients = [];
        do {
            $email = $this->io->ask('Enter recipient email address', $existingEmail);
            if ($email) {
                $recipients[] = $email;
            }

            $addMore = $this->io->confirm('Add another recipient?', false);
        } while ($addMore);

        return $recipients;
    }

    private function askStorageMode(): string
    {
        $this->io->section('Storage Mode');

        $choice = $this->io->choice(
            'How should contact messages be handled?',
            [
                'email' => 'Send email only (no database storage)',
                'database' => 'Save to database only (no email)',
                'both' => 'Send email AND save to database (recommended)',
            ],
            'both'
        );

        return $choice;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function askEntityConfiguration(): ?array
    {
        $this->io->section('Database Configuration');

        if (!$this->entityManager) {
            $this->io->warning('Doctrine ORM is not available. Skipping entity configuration.');
            return null;
        }

        // First question: bundle's entity or existing entity?
        $useOwnEntity = $this->io->confirm(
            'Use the bundle\'s built-in ContactMessageEntity (recommended for quick setup)?',
            true
        );

        if ($useOwnEntity) {
            return [
                'use_existing' => false,
                'class' => 'Caeligo\\ContactUsBundle\\Entity\\ContactMessageEntity',
            ];
        }

        // User wants to use their own entity; find available ContactMessage entities
        $existingEntities = $this->findContactMessageEntities();

        if (empty($existingEntities)) {
            $this->io->warning('No existing ContactMessage entities found in your application.');
            $this->io->info('Please create an entity that implements ContactMessage contract or use the bundle\'s entity.');
            $fallback = $this->io->confirm('Fall back to bundle\'s ContactMessageEntity?', true);
            
            if ($fallback) {
                return [
                    'use_existing' => false,
                    'class' => 'Caeligo\\ContactUsBundle\\Entity\\ContactMessageEntity',
                ];
            }

            return null;
        }

        $this->io->info('Available ContactMessage entities:');
        $this->io->listing($existingEntities);

        $entityClass = $this->io->choice('Select entity to use', $existingEntities);
        
        return [
            'use_existing' => true,
            'class' => $entityClass,
            'metadata' => $this->getEntityMetadata($entityClass),
        ];
    }

    /**
     * @return array<string>
     */
    private function findContactMessageEntities(): array
    {
        if (!$this->entityManager) {
            return [];
        }

        $entities = [];
        $allMetadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($allMetadata as $metadata) {
            $className = $metadata->getName();
            if (str_contains(strtolower($className), 'contactmessage')) {
                $entities[] = $className;
            }
        }

        return $entities;
    }

    /**
     * @return array<string, mixed>
     */
    private function getEntityMetadata(string $entityClass): array
    {
        if (!$this->entityManager) {
            return [];
        }

        $metadata = $this->entityManager->getClassMetadata($entityClass);
        $fields = [];

        foreach ($metadata->getFieldNames() as $fieldName) {
            $mapping = $metadata->getFieldMapping($fieldName);
            $fields[$fieldName] = [
                'type' => $mapping['type'] ?? 'string',
                'nullable' => $mapping['nullable'] ?? false,
                'length' => $mapping['length'] ?? null,
            ];
        }

        return $fields;
    }

    /**
     * @param array<string, mixed>|null $entityConfig
     * @return array<string, mixed>
     */
    private function askFormFields(?array $entityConfig): array
    {
        $this->io->section('Form Fields Configuration');

        // If entity is configured, auto-detect fields
        if ($entityConfig && isset($entityConfig['metadata'])) {
            $autoDetect = $this->io->confirm('Auto-detect form fields from entity?', true);

            if ($autoDetect) {
                return $this->generateFieldsFromEntity($entityConfig['metadata']);
            }
        }

        // Manual field configuration
        $this->io->info('Using default fields (name, email, subject, message).');
        $useDefaults = $this->io->confirm('Use default field configuration?', true);

        if ($useDefaults) {
            return $this->getDefaultFields();
        }

        // TODO: Add manual field builder
        return $this->getDefaultFields();
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function generateFieldsFromEntity(array $metadata): array
    {
        $fields = [];
        $fieldMapping = [
            'title' => ['name' => 'name', 'type' => 'text', 'label' => 'Name'],
            'name' => ['name' => 'name', 'type' => 'text', 'label' => 'Name'],
            'email' => ['name' => 'email', 'type' => 'email', 'label' => 'Email'],
            'phone' => ['name' => 'phone', 'type' => 'tel', 'label' => 'Phone'],
            'subject' => ['name' => 'subject', 'type' => 'text', 'label' => 'Subject'],
            'subtitle' => ['name' => 'subject', 'type' => 'text', 'label' => 'Subject'],
            'body' => ['name' => 'message', 'type' => 'textarea', 'label' => 'Message'],
            'message' => ['name' => 'message', 'type' => 'textarea', 'label' => 'Message'],
        ];

        foreach ($metadata as $fieldName => $fieldInfo) {
            $normalizedName = strtolower($fieldName);

            if (isset($fieldMapping[$normalizedName])) {
                $config = $fieldMapping[$normalizedName];
                $fields[$config['name']] = [
                    'type' => $config['type'],
                    'required' => !($fieldInfo['nullable'] ?? false),
                    'label' => 'contact.field.' . $config['name'],
                    'constraints' => $this->generateConstraints($config['type'], $fieldInfo),
                ];
            }
        }

        // Ensure minimum fields
        if (!isset($fields['name'])) {
            $fields['name'] = $this->getDefaultFields()['name'];
        }
        if (!isset($fields['email'])) {
            $fields['email'] = $this->getDefaultFields()['email'];
        }
        if (!isset($fields['message'])) {
            $fields['message'] = $this->getDefaultFields()['message'];
        }

        $this->io->info('Auto-detected fields: ' . implode(', ', array_keys($fields)));

        return $fields;
    }

    /**
     * @param array<string, mixed> $fieldInfo
     * @return array<int, array<string, mixed>>
     */
    private function generateConstraints(string $type, array $fieldInfo): array
    {
        $constraints = [];

        if (!($fieldInfo['nullable'] ?? false)) {
            $constraints[] = ['NotBlank' => []];
        }

        if ($type === 'email') {
            $constraints[] = ['Email' => []];
        }

        if (isset($fieldInfo['length']) && $fieldInfo['length']) {
            $constraints[] = ['Length' => ['max' => $fieldInfo['length']]];
        }

        if ($type === 'textarea') {
            $constraints[] = ['Length' => ['min' => 10, 'max' => 5000]];
        }

        return $constraints;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultFields(): array
    {
        return [
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
        ];
    }

    private function askCrudRoutePrefix(): string
    {
        $this->io->section('Admin CRUD Routes Configuration');
        $this->io->writeln('<fg=cyan>Specify the base URL path for admin CRUD routes</>');
        $this->io->writeln('Example: /admin/contact, /dashboard/messages, /backend/contact-messages, etc.');
        $this->io->writeln('');

        $prefix = $this->io->ask(
            'Base route prefix for admin CRUD',
            '/admin/contact',
            function($input) {
                // Validate: must start with /
                if (!str_starts_with($input, '/')) {
                    throw new \RuntimeException('Route prefix must start with / (e.g., /admin/contact)');
                }
                // Validate: no trailing slash
                if (str_ends_with($input, '/') && strlen($input) > 1) {
                    throw new \RuntimeException('Route prefix must not end with /');
                }
                return $input;
            }
        );

        $this->io->info("CRUD routes will be available at:");
        $this->io->listing([
            "{$prefix} - List all messages",
            "{$prefix}/<id> - View message",
            "{$prefix}/<id>/edit - Edit message",
            "{$prefix}/<id>/delete - Delete message",
        ]);

        return $prefix;
    }

    /**
     * @return array<string, mixed>
     */
    private function askSpamProtection(string $storageMode): array
    {
        $this->io->section('Spam Protection');
        $this->io->text('Base protections are always enabled: Honeypot, Timing check, and Rate limiting.');
        $this->io->newLine();

        // Ask only about captcha enablement
        $enableCaptcha = $this->io->confirm('Enable captcha protection?', false);

        $captcha = [
            'provider' => 'none',
            'site_key' => null,
            'secret_key' => null,
        ];

        if ($enableCaptcha) {
            $provider = $this->io->choice(
                'Select captcha provider',
                ['turnstile', 'hcaptcha', 'recaptcha', 'friendly'],
                'turnstile'
            );

            $captcha['provider'] = $provider;

            // Ask for keys when relevant
            $captcha['site_key'] = $this->io->ask('Captcha site/public key', null);
            $captcha['secret_key'] = $this->io->ask('Captcha secret key', null);
        }

        return [
            'rate_limit' => [
                'limit' => 3,
                'interval' => '15 minutes',
            ],
            'min_submit_time' => 3,
            'captcha' => $captcha,
        ];
    }

    /**
     * Ask about email verification (only for BOTH storage mode)
     *
     * @return array{enabled: bool, token_ttl: string}
     */
    private function askEmailVerification(string $storageMode): array
    {
        $enabled = false;

        if ($storageMode === 'both') {
            $this->io->section('Email Verification');
            $this->io->text('If enabled, messages are stored as unverified and admin notifications are delayed until the sender verifies their email.');
            $enabled = $this->io->confirm('Enable email verification (recommended for BOTH mode)?', false);
        } else {
            // Not applicable for email-only or database-only
            $enabled = false;
        }

        // Default TTL to 24 hours per product decision
        $ttl = '24 hours';

        if ($enabled) {
            $ttl = $this->io->ask('Verification token TTL (e.g., "24 hours", "12 hours")', '24 hours') ?? '24 hours';
        }

        return [
            'enabled' => $enabled,
            'token_ttl' => $ttl,
        ];
    }

    /**
     * @param array<string, mixed> $emailVerificationConfig
     * @return array<string, mixed>
     */
    private function askMailerConfiguration(array $emailVerificationConfig = []): array
    {
        $this->io->section('Mailer Configuration');

        $existingFrom = $_ENV['CONTACT_FROM_EMAIL'] ?? $_ENV['CONTACT_EMAIL'] ?? null;

        $fromEmail = $this->io->ask(
            'From email address (leave empty to use sender\'s email)',
            $existingFrom ? "'%env(CONTACT_EMAIL)%'" : null
        );

        $this->io->writeln('');
        $this->io->writeln('<fg=cyan>From name</> - The sender name displayed in recipient\'s email client');
        $fromName = $this->io->ask('From name (e.g., "My Website" or "Support Team")', 'Contact Form');

        $subjectPrefix = $this->io->ask('Email subject prefix', '[Contact Form]');

        // If email verification is enabled, sender always gets email (with verification link)
        // so send_copy_to_sender question is irrelevant
        $emailVerificationEnabled = $emailVerificationConfig['enabled'] ?? false;
        
        if ($emailVerificationEnabled) {
            $this->io->writeln('');
            $this->io->writeln('<fg=yellow>Note:</> Email verification is enabled, so senders will automatically receive');
            $this->io->writeln('<fg=yellow>      </> a verification email containing their message and a confirmation link.');
            $sendCopyToSender = true;
        } else {
            $sendCopyToSender = $this->io->confirm('Send a copy of the message to the sender email address?', false);
        }

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject_prefix' => $subjectPrefix,
            'send_copy_to_sender' => $sendCopyToSender,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMailerDefaults(): array
    {
        return [
            'from_email' => null,
            'from_name' => 'Contact Form',
            'subject_prefix' => '[Contact Form]',
            'send_copy_to_sender' => false,
        ];
    }

    /**
     * Build default configuration in non-interactive mode
     *
     * @return array<string, mixed>
     */
    private function buildNonInteractiveConfig(): array
    {
        // Ensure CONTACT_EMAIL exists in .env
        $envEmail = $_ENV['CONTACT_EMAIL'] ?? null;
        $envFile = $this->projectDir . '/.env';
        if ($envEmail === null) {
            $defaultEmail = 'postmaster@example.com';
            // Append to .env if not present
            if (file_exists($envFile)) {
                $contents = file_get_contents($envFile) ?: '';
                if (strpos($contents, 'CONTACT_EMAIL=') === false) {
                    file_put_contents($envFile, "\nCONTACT_EMAIL={$defaultEmail}\n", FILE_APPEND);
                }
            } else {
                file_put_contents($envFile, "CONTACT_EMAIL={$defaultEmail}\n");
            }
        }

        // Defaults
        $config = [];
        $config['recipients'] = ["'%env(CONTACT_EMAIL)%'"];
        $config['storage'] = 'both';
        $config['entity'] = [
            'use_existing' => false,
            'class' => 'Caeligo\\ContactUsBundle\\Entity\\ContactMessageEntity',
        ];
        $config['fields'] = $this->getDefaultFields();
        $config['spam_protection'] = [
            'rate_limit' => [
                'limit' => 3,
                'interval' => '15 minutes',
            ],
            'min_submit_time' => 3,
            'captcha' => [
                'provider' => 'none',
                'site_key' => null,
                'secret_key' => null,
            ],
        ];
        $config['email_verification'] = [
            'enabled' => false,
            'token_ttl' => '24 hours',
        ];
        $config['mailer'] = [
            'from_email' => "'%env(CONTACT_EMAIL)%'",
            'from_name' => 'Contact Form',
            'subject_prefix' => '[Contact Form]',
            'send_copy_to_sender' => false,
        ];

        return $config;
    }

    /**
     * Offer dropping the bundle's table when storage is set to email-only or when switching from bundle entity to custom entity.
     * This guards with a double confirmation (prompt + random code) to avoid accidental data loss.
     */
    private function offerBundleTableDropForEmailStorage(): void
    {
        if (!$this->entityManager) {
            return;
        }

        $tableName = $this->getBundleTableName();
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();

        if (!$schemaManager->tablesExist([$tableName])) {
            return;
        }

        $this->io->section('Database Cleanup');
        $this->io->warning(sprintf("Detected bundle table '%s' while storage is set to email-only. This table is unused in email mode.", $tableName));

        $confirmDrop = $this->io->confirm(
            sprintf("Do you want to drop table '%s' and ALL of its data?", $tableName),
            false
        );

        if (!$confirmDrop) {
            return;
        }

        $code = $this->generateConfirmationCode();
        $this->io->writeln(sprintf('Type this confirmation code to proceed: <info>%s</info>', $code));
        $userCode = $this->io->ask('Confirmation code');

        if ($userCode !== $code) {
            $this->io->warning('Confirmation code mismatch. Table was NOT dropped.');
            return;
        }

        try {
            $schemaManager->dropTable($tableName);
            $this->io->success(sprintf("Dropped table '%s'.", $tableName));
        } catch (\Throwable $e) {
            $this->io->error('Table drop failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the bundle's ContactMessage table name via Doctrine metadata when available.
     */
    private function getBundleTableName(): string
    {
        $default = 'contact_message';

        if (!$this->entityManager) {
            return $default;
        }

        $bundleEntity = 'Caeligo\\ContactUsBundle\\Entity\\ContactMessageEntity';

        try {
            $metadata = $this->entityManager->getClassMetadata($bundleEntity);
            return $metadata->getTableName();
        } catch (MappingException) {
            return $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function generateConfirmationCode(int $length = 6): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Handle database migrations interactively
     */
    private function handleMigrations(): void
    {
        $this->io->section('Generating Migration');
        
        try {
            // Run make:migration
            $process = new Process(
                ['php', 'bin/console', 'make:migration', '--no-interaction'],
                $this->projectDir
            );
            $process->run();

            if ($process->isSuccessful()) {
                $this->io->success('Migration generated successfully!');
                
                // Run migrations
                $this->io->section('Running Migrations');
                $migrateProcess = new Process(
                    ['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'],
                    $this->projectDir
                );
                $migrateProcess->run();

                if ($migrateProcess->isSuccessful()) {
                    $this->io->success('Migrations executed successfully!');
                } else {
                    $this->io->error('Migration execution failed:');
                    $this->io->writeln($migrateProcess->getErrorOutput());
                }
            } else {
                $this->io->warning('No migrations to generate, or migration generation skipped.');
                $this->io->writeln($process->getErrorOutput());
            }
        } catch (\Exception $e) {
            $this->io->error('Error during migration: ' . $e->getMessage());
            $this->io->warning('Please run migrations manually:');
            $this->io->listing([
                'php bin/console make:migration',
                'php bin/console doctrine:migrations:migrate',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function saveConfiguration(array $config): void
    {
        $configFile = $this->projectDir . '/config/packages/contact_us.yaml';
        $configDir = dirname($configFile);

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        // Build YAML with inline comments
        $yaml = "# ContactUs Bundle Configuration\n";
        $yaml .= "# Generated by contact:setup wizard\n\n";
        $yaml .= "contact_us:\n";
        $yaml .= "  # Email recipients for contact form submissions\n";
        $yaml .= "  recipients:\n";
        foreach ($config['recipients'] as $recipient) {
            $yaml .= "    - {$recipient}\n";
        }
        $yaml .= "\n";
        $yaml .= "  # Subject prefix for notification emails\n";
        $yaml .= "  subject_prefix: '" . ($config['mailer']['subject_prefix'] ?? '[Contact Form]') . "'\n\n";
        $yaml .= "  # Storage mode: 'email' (send only), 'database' (save only), or 'both' (recommended)\n";
        $yaml .= "  storage: " . $config['storage'] . "\n\n";
        $yaml .= "  # Spam protection settings\n";
        $yaml .= "  # Base protections always enabled: Honeypot, Timing check, Rate limiting\n";
        $yaml .= "  spam_protection:\n";
        $yaml .= "    rate_limit:\n";
        $yaml .= "      limit: " . $config['spam_protection']['rate_limit']['limit'] . "\n";
        $yaml .= "      interval: '" . $config['spam_protection']['rate_limit']['interval'] . "'\n";
        $yaml .= "    min_submit_time: " . $config['spam_protection']['min_submit_time'] . "\n";
        $yaml .= "    captcha:\n";
        $yaml .= "      provider: " . ($config['spam_protection']['captcha']['provider'] ?? 'none') . "\n";
        $yaml .= "      site_key: " . ($config['spam_protection']['captcha']['site_key'] ?? '~') . "\n";
        $yaml .= "      secret_key: " . ($config['spam_protection']['captcha']['secret_key'] ?? '~') . "\n\n";

        // Email verification block
        $yaml .= "  email_verification:\n";
        $yaml .= "    enabled: " . (($config['email_verification']['enabled'] ?? false) ? 'true' : 'false') . "\n";
        $yaml .= "    token_ttl: '" . ($config['email_verification']['token_ttl'] ?? '24 hours') . "'\n\n";
        $yaml .= "  # Form field configuration\n";
        $yaml .= "  fields:\n";
        foreach ($config['fields'] as $fieldName => $fieldConfig) {
            $yaml .= "    {$fieldName}:\n";
            $yaml .= "      type: " . $fieldConfig['type'] . "\n";
            $yaml .= "      required: " . ($fieldConfig['required'] ? 'true' : 'false') . "\n";
            $yaml .= "      label: " . $fieldConfig['label'] . "\n";
            $yaml .= "      constraints:\n";
            foreach ($fieldConfig['constraints'] as $constraint) {
                foreach ($constraint as $constraintName => $constraintOptions) {
                    if (empty($constraintOptions)) {
                        $yaml .= "        - {$constraintName}: {  }\n";
                    } else {
                        $yaml .= "        - {$constraintName}: { ";
                        $parts = [];
                        foreach ($constraintOptions as $key => $value) {
                            if (is_string($value)) {
                                $parts[] = "{$key}: '{$value}'";
                            } else {
                                $parts[] = "{$key}: {$value}";
                            }
                        }
                        $yaml .= implode(', ', $parts) . " }\n";
                    }
                }
            }
        }
        $yaml .= "\n  # Mailer settings\n";
        $yaml .= "  mailer:\n";
        $fromEmail = $config['mailer']['from_email'] ?? null;
        $yaml .= "    from_email: " . ($fromEmail !== null ? $fromEmail : "~") . "\n";
        $yaml .= "    from_name: '" . ($config['mailer']['from_name'] ?? 'Contact Form') . "'\n";
        $yaml .= "    send_copy_to_sender: " . ($config['mailer']['send_copy_to_sender'] ? 'true' : 'false') . "\n";

        // Persist entity class selection (bundle or existing)
        if (isset($config['entity']['class'])) {
            $yaml .= "\n  # Database entity class\n";
            $yaml .= "  entity_class: " . $config['entity']['class'] . "\n";
        }

        // Persist CRUD route prefix
        if (isset($config['crud_route_prefix'])) {
            $yaml .= "\n  # Admin CRUD routes base path (only if using bundle entity with database storage)\n";
            $yaml .= "  crud_route_prefix: " . $config['crud_route_prefix'] . "\n";
        }

        file_put_contents($configFile, $yaml);
    }

    /**
     * Import bundle routes to config/routes.yaml
     */
    private function importRoutesToConfig(): void
    {
        $routesFile = $this->projectDir . '/config/routes.yaml';

        if (!file_exists($routesFile)) {
            $this->io->warning('config/routes.yaml not found. Please create it manually.');
            return;
        }

        $content = file_get_contents($routesFile);
        if ($content === false) {
            $this->io->warning('Could not read config/routes.yaml');
            return;
        }

        // Check if already imported
        if (strpos($content, '@ContactUsBundle/config/routes.php') !== false) {
            $this->io->info('Routes already imported to config/routes.yaml');
            return;
        }

        // Parse existing routes to detect locale prefix pattern
        $existingConfig = Yaml::parse($content);
        $hasLocalePrefix = false;
        $localePrefixes = ['fr' => '', 'en' => '/en', 'hu' => '/hu'];

        // Detect if any existing route uses locale prefix
        if (is_array($existingConfig)) {
            foreach ($existingConfig as $routeConfig) {
                if (isset($routeConfig['prefix']) && is_array($routeConfig['prefix'])) {
                    $hasLocalePrefix = true;
                    $localePrefixes = $routeConfig['prefix'];
                    break;
                }
            }
        }

        // Build route import
        $routeImport = "\n# ContactUs Bundle Routes (auto-imported by contact:setup)\n";
        $routeImport .= "contact_us:\n";
        $routeImport .= "    resource: '@ContactUsBundle/config/routes.php'\n";
        $routeImport .= "    type: php\n";

        if ($hasLocalePrefix) {
            $routeImport .= "    prefix:\n";
            foreach ($localePrefixes as $locale => $prefix) {
                $routeImport .= "        {$locale}: '{$prefix}'\n";
            }
        }

        // Append to routes.yaml
        file_put_contents($routesFile, $content . $routeImport);
    }

    /**
     * Import admin CRUD routes to config/routes.yaml when using bundle entity + database storage
     */
    private function importCrudRoutesToConfig(string $crudRoutePrefix = '/admin/contact'): void
    {
        $routesFile = $this->projectDir . '/config/routes.yaml';

        if (!file_exists($routesFile)) {
            $this->io->warning('config/routes.yaml not found. Please create it manually.');
            return;
        }

        $content = file_get_contents($routesFile);
        if ($content === false) {
            $this->io->warning('Could not read config/routes.yaml');
            return;
        }

        // Check if already imported
        $alreadyImported = strpos($content, '@ContactUsBundle/config/routes_crud.php') !== false;

        // Parse existing routes to detect locale prefix pattern
        $existingConfig = Yaml::parse($content);
        $hasLocalePrefix = false;
        $localePrefixes = ['fr' => '', 'en' => '/en', 'hu' => '/hu'];

        if (is_array($existingConfig)) {
            foreach ($existingConfig as $routeConfig) {
                if (isset($routeConfig['prefix']) && is_array($routeConfig['prefix'])) {
                    $hasLocalePrefix = true;
                    $localePrefixes = $routeConfig['prefix'];
                    break;
                }
            }
        }

        $routeImport = "\n# ContactUs Bundle Admin Routes (auto-imported by contact:setup)\n";
        $routeImport .= "# Base path configured during setup: {$crudRoutePrefix}\n";
        $routeImport .= "contact_us_admin:\n";
        $routeImport .= "    resource: '@ContactUsBundle/config/routes_crud.php'\n";
        $routeImport .= "    type: php\n";
        $routeImport .= "    prefix:\n";

        if ($hasLocalePrefix) {
            // Append CRUD prefix to each locale prefix
            foreach ($localePrefixes as $locale => $localePrefix) {
                $fullPrefix = rtrim($localePrefix, '/') . $crudRoutePrefix;
                $routeImport .= "        {$locale}: '{$fullPrefix}'\n";
            }
        } else {
            // No locale prefix, just use the CRUD prefix directly
            $routeImport .= "        default: '{$crudRoutePrefix}'\n";
        }

        if ($alreadyImported) {
            // Update existing routes - remove old contact_us_admin and add new one
            // Find and remove the contact_us_admin section
            $content = preg_replace(
                '/\n# ContactUs Bundle Admin Routes.*?(?=\n[a-z_]+:|$)/s',
                '',
                $content
            );
            file_put_contents($routesFile, $content . $routeImport);
            $this->io->info('Admin CRUD routes updated in config/routes.yaml');
        } else {
            // First time import
            file_put_contents($routesFile, $content . $routeImport);
            $this->io->info('Admin CRUD routes imported to config/routes.yaml');
        }
    }

    /**
     * Clear cache and compile assets if AssetMapper is available
     */
    private function clearCacheAndCompileAssets(): void
    {
        try {
            $this->io->writeln('');
            $this->io->writeln('<comment>Clearing cache...</comment>');
            
            // Clear cache
            $clearCacheProcess = new Process(
                ['php', 'bin/console', 'cache:clear', '--no-warmup'],
                $this->projectDir
            );
            $clearCacheProcess->run();

            if ($clearCacheProcess->isSuccessful()) {
                $this->io->writeln('<info>✓ Cache cleared successfully</info>');
            } else {
                $this->io->writeln('<error>✗ Cache clear failed:</error>');
                $this->io->writeln($clearCacheProcess->getErrorOutput());
            }

            // Try to compile assets if AssetMapper is available
            $this->io->writeln('<comment>Compiling assets...</comment>');
            $assetCompileProcess = new Process(
                ['php', 'bin/console', 'asset-map:compile', '--force'],
                $this->projectDir
            );
            $assetCompileProcess->run();

            if ($assetCompileProcess->isSuccessful()) {
                $this->io->writeln('<info>✓ Assets compiled successfully</info>');
            } else {
                // Asset compilation is optional, don't show error if command doesn't exist
                if (strpos($assetCompileProcess->getErrorOutput(), 'There are no commands') === false) {
                    $this->io->writeln('<comment>Note: Asset compilation skipped (AssetMapper not available)</comment>');
                }
            }
        } catch (\Exception $e) {
            $this->io->error('Cache/asset operations failed: ' . $e->getMessage());
        }
    }
}
