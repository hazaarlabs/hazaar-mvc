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

    /**
     * @var array<array<mixed|string>>
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

    /*
     * @var array<string, array<mixed>>
    private static array $tableMap = [
        'extension' => ['extensions', false, null],
        'sequence' => ['sequences', false, null],
        'table' => ['tables', 'cols', null],
        'view' => ['views', true, 'views'],
        'constraint' => ['constraints', true, null],
        'index' => ['indexes', true, null],
        'function' => ['functions', false, 'functions'],
        'trigger' => ['triggers', true, 'functions'],
    ];
    */

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
        $this->workDir = Loader::getFilePath(FilePath::DB);
        if (!is_dir($this->workDir)) {
            $this->workDir = getcwd().DIRECTORY_SEPARATOR.'db';
        }
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
    public function getVersions(): array
    {
        if (isset($this->versions)) {
            return $this->versions;
        }
        $migrateDir = $this->workDir.DIRECTORY_SEPARATOR.'migrate';
        if (!file_exists($migrateDir) && is_dir($migrateDir)) {
            return [];
        }
        $this->versions = [];
        $dir = dir($migrateDir);
        while ($file = $dir->read()) {
            if ('.' === substr($file, 0, 1)) {
                continue;
            }
            $info = pathinfo($file);
            $matches = [];
            if (!(isset($info['extension']) && 'json' === $info['extension'] && preg_match('/^(\d+)_(\w+)$/', $info['filename'], $matches))) {
                continue;
            }
            $version = Version::loadFromFile($migrateDir.DIRECTORY_SEPARATOR.$file);
            $this->versions[$version->number] = $version;
        }
        ksort($this->versions);

        return $this->versions;
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
    public function getVersion(): ?Version
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
        if (($currentVersion = $this->getVersion()) === null) {
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
        return false;
    }

    /**
     * Undo a migration version.
     *
     * @param array<int> $rollbacks
     */
    public function rollback(
        int $version,
        bool $test = false,
        array &$rollbacks = []
    ): bool {
        return false;
    }

    public function replay(
        int $version,
        bool $test = false
    ): bool {
        return false;
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

    public function snapshot(
        ?string $comment = null,
        bool $test = false,
        ?int $overrideVersion = null
    ): bool {
        return false;
    }

    public function checkpoint(): bool
    {
        return false;
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
        if (!$this->dbi->tableExists(self::$schemaInfoTable)) {
            $this->log('Creating schema info table');
            $this->dbi->table(self::$schemaInfoTable)->create([
                'number' => [
                    'data_type' => 'bigint',
                    'not_null' => true,
                    'primarykey' => true,
                ],
                'applied_on' => [
                    'data_type' => 'timestamp with time zone',
                    'not_null' => true,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
                'description' => [
                    'data_type' => 'text',
                    'not_null' => false,
                ],
                'migrate_down' => [
                    'data_type' => 'jsonb',
                    'null' => true,
                ],
            ]);
        }

        return true;
    }

    /**
     * Logs a message to the migration log.
     *
     * @param string $msg The message to log
     */
    private function log(string $msg): void
    {
        $entry = new LogEntry($msg);
        $this->migrationLog[] = $entry;
        if (!isset($this->__callback)) {
            return;
        }
        while ($entry = array_shift($this->migrationLog)) {
            call_user_func($this->__callback, $entry);
        }
    }
}
