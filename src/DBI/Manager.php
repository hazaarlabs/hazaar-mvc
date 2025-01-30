<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/SchemaManager.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI;

use Hazaar\Application\FilePath;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Manager\LogEntry;
use Hazaar\DBI\Manager\Schema;
use Hazaar\DBI\Manager\Snapshot;
use Hazaar\DBI\Manager\Version;
use Hazaar\Loader;

/**
 * Relational Database Schema Manager.
 */
class Manager
{
    public const MACRO_VARIABLE = 1;
    public const MACRO_LOOKUP = 2;
    public static string $schemaInfoTable = 'schema_version';
    private Adapter $dbi;

    /**
     * @var array<mixed>
     */
    private array $config;
    private string $workDir;
    private string $migrateDir;

    /**
     * @var array<LogEntry>
     */
    private array $migrationLog = [];

    /**
     * @var array<int,Version>
     */
    private array $versions;
    private Version $currentVersion;

    /**
     * @var array<int,Version>
     */
    private array $appliedVersions;

    /**
     * @var array<int, Version>
     */
    private array $missingVersions;
    private \Closure $__callback;

    /**
     * @param array<mixed> $dbiConfig
     */
    public function __construct(array $dbiConfig, ?\Closure $logCallback = null)
    {
        if ($logCallback) {
            $this->registerLogHandler($logCallback);
        }
        if (!isset($dbiConfig['environment'])) {
            $dbiConfig['environment'] = APPLICATION_ENV;
        }
        $this->config = array_merge($dbiConfig, $dbiConfig['manager'] ?? []);
        $workDir = Loader::getFilePath(FilePath::DB);
        if (file_exists($workDir)) {
            if (!is_dir($workDir)) {
                throw new \Exception('DB directory exists but is not a directory!');
            }
        } else {
            if (!mkdir($workDir, 0777, true)) {
                throw new \Exception('Failed to create DB directory!');
            }
        }
        $this->workDir = $workDir;
        $this->migrateDir = $this->workDir.DIRECTORY_SEPARATOR.'migrate';
    }

    /**
     * Sets the callback function to be called when a log entry is added.
     *
     * @param \Closure $callback The callback function
     */
    public function registerLogHandler(\Closure $callback): void
    {
        $this->__callback = $callback;
    }

    /**
     * Gets all the available schema versions from the migration directory.
     *
     * @return array<Version> The available schema versions
     */
    public function getVersions(bool $mergeApplied = false): array
    {
        if (isset($this->versions)) {
            return $this->versions;
        }

        if (!file_exists($this->migrateDir) && is_dir($this->migrateDir)) {
            return [];
        }
        $this->versions = [];
        $dir = dir($this->migrateDir);
        while ($file = $dir->read()) {
            if ('.' === substr($file, 0, 1)) {
                continue;
            }
            $info = pathinfo($file);
            $matches = [];
            if (!(isset($info['extension']) && 'json' === $info['extension'] && preg_match('/^(\d+)_(\w+)$/', $info['filename'], $matches))) {
                continue;
            }
            $version = Version::loadFromFile($this->migrateDir.DIRECTORY_SEPARATOR.$file);
            $this->versions[$version->number] = $version;
        }
        if ($mergeApplied) {
            $applied = $this->getAppliedVersions();
            foreach ($applied as $version) {
                if (array_key_exists($version->number, $this->versions)) {
                    continue;
                }
                $this->versions[$version->number] = $version;
            }
        }
        ksort($this->versions);

        return $this->versions;
    }

    public function getVersion(int $version): ?Version
    {
        $versions = $this->getVersions();
        if (!array_key_exists($version, $versions)) {
            return null;
        }

        return $versions[$version];
    }

    /**
     * Retrieves the list of schema versions that have been applied to the database.
     *
     * @return array<Version> an array of applied version identifiers
     */
    public function getAppliedVersions(): array
    {
        if (isset($this->appliedVersions)) {
            return $this->appliedVersions;
        }
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $versions = $this->getVersions();
        $this->appliedVersions = $this->dbi->table(self::$schemaInfoTable)->fetchAllModel(Version::class, 'number');
        array_walk($this->appliedVersions, function (Version $version) use ($versions) {
            if (!array_key_exists($version->number, $versions)) {
                $version->valid = false;
            }
        });

        return $this->appliedVersions;
    }

    public function getAppliedVersion(int $version): ?Version
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }

        /**
         * @var false|Version $version
         */
        $version = $this->dbi->table(self::$schemaInfoTable)->findOneModel(Version::class, ['number' => $version]);
        if (false === $version) {
            return null;
        }

        return $version;
    }

    /**
     * Retrieves the list of schema versions that have not been applied to the database.
     *
     * @return array<Version>
     */
    public function getMissingVersions(?int $version = null): array
    {
        if (isset($this->missingVersions)) {
            return $this->missingVersions;
        }
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $versions = $this->getVersions();
        $appliedVersions = $this->dbi->table(self::$schemaInfoTable)->fetchAllModel(Version::class, 'number');
        $this->missingVersions = array_filter($versions, function ($value, $key) use ($appliedVersions) {
            return !array_key_exists($key, $appliedVersions);
        }, ARRAY_FILTER_USE_BOTH);

        return $this->missingVersions;
    }

    /**
     * Returns the currently applied schema version.
     */
    public function getCurrentVersion(): ?Version
    {
        if (isset($this->currentVersion)) {
            return $this->currentVersion;
        }
        if (!isset($this->dbi)) {
            $this->connect();
        }
        if (false === $this->dbi->table(self::$schemaInfoTable)->exists()) {
            return null;
        }

        /**
         * @var false|Version $version
         */
        $version = $this->dbi->table(self::$schemaInfoTable)->findOneModel(Version::class, [
            'number' => $this->dbi->table(self::$schemaInfoTable)->select(['number' => 'max(number)']),
        ]);
        if (false === $version) {
            return null;
        }

        return $this->currentVersion = $version;
    }

    /**
     * Returns the version number of the latest schema version.
     */
    public function getLatestVersion(): ?Version
    {
        $versions = $this->getVersions();
        if (0 === count($versions)) {
            return null;
        }

        return end($versions);
    }

    /**
     * Boolean indicator for when the current schema version is the latest.
     */
    public function isLatest(): bool
    {
        if (($currentVersion = $this->getCurrentVersion()) === null) {
            return false;
        }
        $latestVersion = $this->getLatestVersion();
        if (null === $latestVersion) {
            return false;
        }

        return $latestVersion->number === $currentVersion->number;
    }

    /**
     * Boolean indicator for when there are migrations that have not been applied.
     */
    public function hasUpdates(): bool
    {
        return count($this->getMissingVersions()) > 0;
    }

    public function getSchema(bool $allSchema = false): ?Schema
    {
        $versions = $allSchema ? $this->getVersions() : $this->getAppliedVersions();
        if (0 === count($versions)) {
            return null;
        }

        return Schema::load($versions);
    }

    public function migrate(
        ?int $version = null,
        bool $forceDataSync = false,
        bool $test = false,
        bool $keepTables = false,
        bool $forceReinitialise = false
    ): bool {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $this->log('Starting migration...');
        $versions = $this->getMissingVersions();
        if (0 === count($versions)) {
            $this->log('No updates available.');

            return false;
        }
        $this->log('Found '.count($versions).' updates to apply.');
        foreach ($versions as $version) {
            $this->log('Replaying version '.$version->number.': '.$version->description);
            if (!$version->replay($this->dbi)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Undo a migration version.
     *
     * @param array<int> $rollbacks
     */
    public function rollback(
        int $versionNumber,
        bool $test = false,
        array &$rollbacks = []
    ): bool {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $version = $this->getAppliedVersion($versionNumber);
        if (null === $version) {
            $this->log("Version {$versionNumber} has not been applied.  Skipping rollback.");

            return false;
        }

        return $version->rollback($this->dbi);
    }

    public function replay(
        int $version,
        bool $test = false
    ): bool {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $version = $this->getVersion($version);
        $version->rollback($this->dbi);

        return $version->replay($this->dbi);
    }

    public function snapshot(
        ?string $comment = null,
        bool $test = false,
        ?int $overrideVersion = null
    ): bool {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $this->log('Importing database schema');
        $databaseSchema = Schema::import($this->dbi);
        $this->log('Loading master schema');
        $masterSchema = $this->getSchema(true);
        $snapshot = Snapshot::create($comment ?? 'Snapshot');
        if (null === $masterSchema) {
            $this->log('No master schema found. Creating initial snapshot.');
            $migration = $databaseSchema->toMigration();
        } else {
            $this->log('Comparing schemas');
            $migration = $snapshot->compare($masterSchema, $databaseSchema);
            if (null === $migration || !isset($migration->up) || 0 === count($migration->up->actions)) {
                $this->log('No changes detected.');

                return false;
            }
        }
        $this->log('Found '.count($migration->up->actions).' changes to apply');
        if (true === $test) {
            return true;
        }
        $version = date('YmdHis').'_'.str_replace(' ', '_', $comment ?? 'Snapshot');
        $this->log('Setting version to '.$version);
        $migrateFile = $this->migrateDir.DIRECTORY_SEPARATOR.$version.'.json';
        $this->log('Writing migration file: '.$migrateFile);
        $result = file_put_contents($migrateFile, $migration->toJSON(JSON_PRETTY_PRINT));
        if (!$result) {
            $this->log('Failed to write migration file: '.$migrateFile);

            return false;
        }

        return true;
    }

    /**
     * @param array<mixed> $dataSchema
     */
    public function sync(
        ?array $dataSchema = null,
        bool $test = false,
        bool $forceDataSync = false
    ): bool {
        return false;
    }

    public function checkpoint(): bool
    {
        return false;
    }

    /**
     * Drops all database objects.
     *
     * WARNING: This will delete all tables, views, functions, and extensions in the database!
     */
    public function deleteEverything(): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $this->log('WARNING: Deleting all database objects!');
        $views = $this->dbi->listViews();
        foreach ($views as $view) {
            $this->dbi->dropView($view['name']);
        }
        $lastTables = [];
        for ($i = 0; $i < 255; ++$i) {
            $tables = $this->dbi->listTables();
            if (0 === count($tables)) {
                break;
            }
            if (count($tables) === count($lastTables) && $i > count($tables)) {
                $this->log('Got stuck trying to resolve drop dependencies. Aborting!');

                return false;
            }
            foreach ($tables as $table) {
                try {
                    $this->dbi->dropTable($table['name']);
                } catch (\Throwable $e) {
                }
            }
            $lastTables = $tables;
        }
        if (254 === $i) {
            return false;
        }
        $functions = $this->dbi->listFunctions(true);
        foreach ($functions as $function) {
            $this->dbi->dropFunction($function['name'], $function['parameters'], true);
        }
        $extensions = $this->dbi->listExtensions();
        foreach ($extensions as $extension) {
            $this->dbi->dropExtension($extension);
        }

        return $this->createSchemaVersionTable();
    }

    /**
     * Returns the migration log.
     *
     * @return array<LogEntry>
     */
    public function getMigrationLog(): array
    {
        return $this->migrationLog;
    }

    /**
     * Connects to the database.
     *
     * @param array<mixed> $config The database configuration
     */
    private function connect(?array $config = null): bool
    {
        if (isset($this->dbi)) {
            return false;
        }
        if (!isset($config)) {
            $config = $this->config;
        }

        try {
            $this->log("Connecting to database '{$this->config['dbname']}' on host '{$config['host']}'");
            $this->dbi = new Adapter($config);
        } catch (ConnectionFailed $e) {
            if (7 !== $e->getCode() || true !== ake($config, 'createDatabase')) {
                throw $e;
            }
            $this->log('Database does not exist.  Attempting to create it as requested.');
            $config['dbname'] = isset($config['maintenanceDatabase'])
                ? $config['maintenanceDatabase']
                : $config['user'];
            $this->log("Connecting to database '{$config['dbname']}' on host '{$config['host']}'");
            $maintDB = new Adapter($config);
            $this->log("Creating database '{$config['dbname']}'");
            $maintDB->createDatabase($config['dbname']);
            $this->log("Retrying connection to database '{$config['dbname']}' on host '{$config['host']}'");
            $this->dbi = new Adapter($config);
        }
        for ($i = 0; $i < 2; ++$i) {
            try {
                $this->createSchemaVersionTable();

                return true;
            } catch (\PDOException $e) {
                if (isset($config['schema']) && '3F000' !== $e->getCode()) {
                    throw $e;
                }
                $this->log('Failed to create schema info table.  Retrying...');
                $this->dbi->createSchema($config['schema']);
            }
        }

        return false;
    }

    private function createSchemaVersionTable(): bool
    {
        if ($this->dbi->tableExists(self::$schemaInfoTable)) {
            return false;
        }
        $this->log('Creating schema info table');

        return $this->dbi->table(self::$schemaInfoTable)->create([
            'number' => [
                'type' => 'bigint',
                'not_null' => true,
                'primarykey' => true,
            ],
            'applied_on' => [
                'type' => 'timestamp with time zone',
                'not_null' => true,
                'default' => 'CURRENT_TIMESTAMP',
            ],
            'description' => [
                'type' => 'text',
                'not_null' => false,
            ],
            'migrate' => [
                'type' => 'jsonb',
                'null' => true,
            ],
        ]);
    }

    /**
     * Logs a message to the migration log.
     *
     * @param string $msg The message to log
     */
    private function log(string $msg): void
    {
        $this->migrationLog[] = new LogEntry($msg);
        if (!isset($this->__callback)) {
            return;
        }
        while ($entry = array_shift($this->migrationLog)) {
            call_user_func($this->__callback, $entry);
        }
    }
}
