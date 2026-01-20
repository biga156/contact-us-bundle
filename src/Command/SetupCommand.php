<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
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

        // Step 1: Email recipients
        $config['recipients'] = $this->askRecipients();

        // Step 2: Storage mode
        $config['storage'] = $this->askStorageMode();

        // Step 3: If database, ask about table/entity
        $usingBundleEntity = false;
        if (in_array($config['storage'], ['database', 'both'], true)) {
            $entityConfig = $this->askEntityConfiguration();
            if ($entityConfig) {
                $config['entity'] = $entityConfig;
                $usingBundleEntity = !($entityConfig['use_existing'] ?? false);
            }
        }

        // Step 4: Form fields (auto-detect or manual)
        $config['fields'] = $this->askFormFields($config['entity'] ?? null);

        // Step 5: Spam protection
        $config['spam_protection'] = $this->askSpamProtection();

        // Step 6: Mailer configuration
        $config['mailer'] = $this->askMailerConfiguration();

        // Save configuration
        $this->saveConfiguration($config);
        $this->importRoutesToConfig();
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

        // Check for existing ContactMessage entities
        $existingEntities = $this->findContactMessageEntities();

        if (!empty($existingEntities)) {
            $this->io->info('Found existing ContactMessage entities:');
            $this->io->listing($existingEntities);

            $useExisting = $this->io->confirm('Use an existing entity instead of creating a new table?', true);

            if ($useExisting) {
                $entityClass = $this->io->choice('Select entity to use', $existingEntities);
                
                return [
                    'use_existing' => true,
                    'class' => $entityClass,
                    'metadata' => $this->getEntityMetadata($entityClass),
                ];
            }
        }

        return [
            'use_existing' => false,
            'class' => 'Caeligo\\ContactUsBundle\\Entity\\ContactMessageEntity',
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
        $this->io->info('Using default fields (name, email, message).');
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

    /**
     * @return array<string, mixed>
     */
    private function askSpamProtection(): array
    {
        $this->io->section('Spam Protection');

        $level = (int) $this->io->choice(
            'Select spam protection level',
            [
                1 => 'Level 1: Honeypot + Rate limiting (recommended)',
                2 => 'Level 2: Level 1 + Email verification',
                3 => 'Level 3: Level 2 + Third-party captcha',
            ],
            1
        );

        return [
            'level' => $level,
            'rate_limit' => [
                'limit' => 3,
                'interval' => '15 minutes',
            ],
            'min_submit_time' => 3,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function askMailerConfiguration(): array
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

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject_prefix' => $subjectPrefix,
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
            'level' => 1,
            'rate_limit' => [
                'limit' => 3,
                'interval' => '15 minutes',
            ],
            'min_submit_time' => 3,
        ];
        $config['mailer'] = [
            'from_email' => "'%env(CONTACT_EMAIL)%'",
            'from_name' => 'Contact Form',
            'subject_prefix' => '[Contact Form]',
        ];

        return $config;
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
        $yaml .= "  spam_protection:\n";
        $yaml .= "    # Level: 1 (honeypot+rate limit), 2 (+email verification), 3 (+captcha)\n";
        $yaml .= "    level: " . $config['spam_protection']['level'] . "\n";
        $yaml .= "    rate_limit:\n";
        $yaml .= "      limit: " . $config['spam_protection']['rate_limit']['limit'] . "\n";
        $yaml .= "      interval: '" . $config['spam_protection']['rate_limit']['interval'] . "'\n";
        $yaml .= "    min_submit_time: " . $config['spam_protection']['min_submit_time'] . "\n\n";
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
                        $yaml .= "        - {$constraintName}: " . Yaml::dump($constraintOptions, 1, 2) . "";
                    }
                }
            }
        }
        $yaml .= "\n  # Mailer settings\n";
        $yaml .= "  mailer:\n";
        $yaml .= "    from_email: " . $config['mailer']['from_email'] . "\n";
        $yaml .= "    from_name: '" . $config['mailer']['from_name'] . "'\n";

        // Add entity class if using bundle's own entity
        if (isset($config['entity']) && !($config['entity']['use_existing'] ?? false)) {
            $yaml .= "\n  # Database entity class (using bundle's entity with cg_ prefix)\n";
            $yaml .= "  entity_class: " . $config['entity']['class'] . "\n";
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
     * Clear cache and compile assets if AssetMapper is available
     */
    private function clearCacheAndCompileAssets(): void
    {
        try {
            // Clear cache
            $clearCacheProcess = new Process(
                ['php', 'bin/console', 'cache:clear', '--no-warmup'],
                $this->projectDir
            );
            $clearCacheProcess->run();

            if ($clearCacheProcess->isSuccessful()) {
                $this->io->writeln('<info>✓ Cache cleared</info>');
            }

            // Try to compile assets if AssetMapper is available
            $assetCompileProcess = new Process(
                ['php', 'bin/console', 'asset-map:compile', '--force'],
                $this->projectDir
            );
            $assetCompileProcess->run();

            if ($assetCompileProcess->isSuccessful()) {
                $this->io->writeln('<info>✓ Assets compiled</info>');
            }
        } catch (\Exception $e) {
            $this->io->writeln('<comment>Note: Cache/asset operations completed with warnings</comment>');
        }
    }
}
