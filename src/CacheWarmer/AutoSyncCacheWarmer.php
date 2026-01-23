<?php

declare(strict_types=1);

namespace Caeligo\ContactUsBundle\CacheWarmer;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Cache warmer that auto-syncs ContactUs configuration in dev mode.
 * 
 * When dev.auto_sync is true and running in debug mode, this warmer
 * detects configuration changes and handles them:
 * 
 * - Storage changes to email-only: logs info about dropping table
 * - Storage changes to database/both: ensures table exists via migration
 * - Other changes are informational only
 * 
 * Note: For interactive operations (like table drop confirmation), 
 * users should use the contact-us:setup wizard instead.
 */
class AutoSyncCacheWarmer implements CacheWarmerInterface
{
    private const BUNDLE_ENTITY = 'Caeligo\\ContactUsBundle\\Entity\\ContactMessageEntity';
    private const STATE_FILE = 'contact_us_previous_state.json';

    public function __construct(
        private string $projectDir,
        private bool $isDebug,
        private bool $autoSyncEnabled,
        private ?EntityManagerInterface $entityManager = null,
    ) {}

    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        // Only run in dev mode with auto_sync enabled
        if (!$this->isDebug || !$this->autoSyncEnabled) {
            return [];
        }

        $configFile = $this->projectDir . '/config/packages/contact_us.yaml';
        if (!file_exists($configFile)) {
            return [];
        }

        try {
            $config = Yaml::parseFile($configFile);
            $currentConfig = is_array($config) ? ($config['contact_us'] ?? null) : null;

            if (!is_array($currentConfig)) {
                return [];
            }

            $previousState = $this->loadPreviousState();
            $this->processConfigChanges($currentConfig, $previousState);
            $this->savePreviousState($currentConfig);
            
        } catch (\Throwable $e) {
            // Log to file since we don't have console output
            $this->logMessage('Auto-sync warning: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * @param array<string, mixed> $currentConfig
     * @param array<string, mixed>|null $previousState
     */
    private function processConfigChanges(array $currentConfig, ?array $previousState): void
    {
        $currentStorage = $currentConfig['storage'] ?? 'email';
        $previousStorage = $previousState['storage'] ?? null;
        $entityClass = $currentConfig['entity_class'] ?? self::BUNDLE_ENTITY;

        // Storage mode changed
        if ($previousStorage !== null && $previousStorage !== $currentStorage) {
            $this->logMessage(sprintf('Storage mode changed: %s → %s', $previousStorage, $currentStorage));
            
            if ($currentStorage === 'email') {
                $this->handleSwitchToEmailOnly($entityClass);
            } elseif (in_array($currentStorage, ['database', 'both'], true)) {
                $this->handleSwitchToDatabase($entityClass);
            }
        } elseif ($previousStorage === null && in_array($currentStorage, ['database', 'both'], true)) {
            // First run with database storage
            $this->handleSwitchToDatabase($entityClass);
        }

        // Check if fields changed
        $currentFields = $currentConfig['fields'] ?? [];
        $previousFields = $previousState['fields'] ?? [];
        if ($currentFields !== $previousFields) {
            $this->logMessage('Form fields updated - changes will be active on next request.');
        }

        // Check if mailer config changed
        $currentMailer = $currentConfig['mailer'] ?? [];
        $previousMailer = $previousState['mailer'] ?? [];
        if ($currentMailer !== $previousMailer) {
            $this->logMessage('Mailer configuration updated.');
        }
    }

    private function handleSwitchToEmailOnly(string $entityClass): void
    {
        if (!$this->entityManager) {
            return;
        }

        $tableName = $this->getTableName($entityClass);
        if (!$tableName) {
            return;
        }

        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
            $this->logMessage(sprintf("Table '%s' does not exist - nothing to drop.", $tableName));
            return;
        }

        // Cannot do interactive confirmation in cache warmer, just log info
        $this->logMessage(sprintf(
            "Storage changed to 'email' - table '%s' is no longer needed. " .
            "Use 'php bin/console contact-us:setup' to drop it interactively, or run: " .
            "php bin/console doctrine:schema:update --dump-sql",
            $tableName
        ));
    }

    private function handleSwitchToDatabase(string $entityClass): void
    {
        if (!$this->entityManager) {
            $this->logMessage('Doctrine ORM not available. Please run migrations manually.');
            return;
        }

        $tableName = $this->getTableName($entityClass);
        if (!$tableName) {
            $this->logMessage('Could not determine table name. Please run migrations manually.');
            return;
        }

        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
        if ($schemaManager->tablesExist([$tableName])) {
            $this->logMessage(sprintf("Table '%s' already exists.", $tableName));
            return;
        }

        $this->logMessage(sprintf("Table '%s' does not exist. Creating...", $tableName));

        // Run migration via subprocess
        $env = $this->getProcessEnv();
        
        try {
            // First try make:migration
            $makeMigration = new Process(
                ['php', 'bin/console', 'make:migration', '--no-interaction'],
                $this->projectDir,
                $env
            );
            $makeMigration->run();

            if ($makeMigration->isSuccessful()) {
                $this->logMessage('Migration generated.');
                
                // Run the migration
                $runMigration = new Process(
                    ['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'],
                    $this->projectDir,
                    $env
                );
                $runMigration->run();

                if ($runMigration->isSuccessful()) {
                    $this->logMessage('Migration executed successfully!');
                } else {
                    $this->logMessage('Migration execution failed. Please run: php bin/console doctrine:migrations:migrate');
                }
            } else {
                // Fallback to schema:update
                $schemaUpdate = new Process(
                    ['php', 'bin/console', 'doctrine:schema:update', '--force'],
                    $this->projectDir,
                    $env
                );
                $schemaUpdate->run();

                if ($schemaUpdate->isSuccessful()) {
                    $this->logMessage('Schema updated successfully!');
                } else {
                    $this->logMessage('Schema update failed. Please create table manually.');
                }
            }
        } catch (\Throwable $e) {
            $this->logMessage('Migration failed: ' . $e->getMessage());
        }
    }

    private function getTableName(string $entityClass): ?string
    {
        if (!$this->entityManager) {
            return null;
        }

        try {
            $metadata = $this->entityManager->getClassMetadata($entityClass);
            return $metadata->getTableName();
        } catch (MappingException) {
            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPreviousState(): ?array
    {
        $stateFile = $this->projectDir . '/var/' . self::STATE_FILE;
        if (!file_exists($stateFile)) {
            return null;
        }

        $content = file_get_contents($stateFile);
        if ($content === false) {
            return null;
        }

        $state = json_decode($content, true);
        return is_array($state) ? $state : null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function savePreviousState(array $config): void
    {
        $stateFile = $this->projectDir . '/var/' . self::STATE_FILE;
        $varDir = dirname($stateFile);
        
        if (!is_dir($varDir)) {
            mkdir($varDir, 0755, true);
        }

        $state = [
            'storage' => $config['storage'] ?? 'email',
            'entity_class' => $config['entity_class'] ?? self::BUNDLE_ENTITY,
            'fields' => $config['fields'] ?? [],
            'mailer' => $config['mailer'] ?? [],
        ];

        file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, string>
     */
    private function getProcessEnv(): array
    {
        $env = $_SERVER;
        
        $envLocalFile = $this->projectDir . '/.env.local';
        if (file_exists($envLocalFile)) {
            $lines = file($envLocalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#')) {
                        continue;
                    }
                    if (str_contains($line, '=')) {
                        [$key, $value] = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        if (!isset($env[$key]) || $env[$key] === '') {
                            $env[$key] = $value;
                        }
                    }
                }
            }
        }
        
        return $env;
    }

    private function logMessage(string $message): void
    {
        $logFile = $this->projectDir . '/var/log/contact_us_auto_sync.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        
        // Also output to console if possible (verbose mode)
        echo "[ContactUs Auto-Sync] $message\n";
    }
}
