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

        $config = [];

        // Step 1: Email recipients
        $config['recipients'] = $this->askRecipients();

        // Step 2: Storage mode
        $config['storage'] = $this->askStorageMode();

        // Step 3: If database, ask about table/entity
        if (in_array($config['storage'], ['database', 'both'], true)) {
            $entityConfig = $this->askEntityConfiguration();
            if ($entityConfig) {
                $config['entity'] = $entityConfig;
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

        $this->io->success('ContactUs bundle configuration saved successfully!');
        $this->io->info('Configuration file: config/packages/contact_us.yaml');
        $this->io->info('Next steps:');
        $this->io->listing([
            'Import routes: Add contact_us resource to config/routes.yaml',
            'Run cache:clear to apply changes',
            'If using database storage, run doctrine:migrations:diff and migrate',
        ]);

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

        $fromName = $this->io->ask('From name', 'Contact Form');

        $subjectPrefix = $this->io->ask('Email subject prefix', '[Contact Form]');

        return [
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'subject_prefix' => $subjectPrefix,
        ];
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

        // Build YAML structure
        $yamlConfig = [
            'contact_us' => [
                'recipients' => $config['recipients'],
                'subject_prefix' => $config['mailer']['subject_prefix'] ?? '[Contact Form]',
                'storage' => $config['storage'],
                'spam_protection' => $config['spam_protection'],
                'fields' => $config['fields'],
                'mailer' => [
                    'from_email' => $config['mailer']['from_email'],
                    'from_name' => $config['mailer']['from_name'],
                ],
            ],
        ];

        // Add entity configuration if exists
        if (isset($config['entity'])) {
            $yamlConfig['contact_us']['entity'] = [
                'use_existing' => $config['entity']['use_existing'],
                'class' => $config['entity']['class'],
            ];
        }

        $yaml = Yaml::dump($yamlConfig, 4, 2);
        file_put_contents($configFile, $yaml);
    }
}
