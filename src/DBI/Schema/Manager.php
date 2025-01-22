<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/SchemaManager.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI\Schema;

use Hazaar\Application\FilePath;
use Hazaar\DBI\Adapter;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Schema\Exception\Schema;
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
    private \Closure $__callback;

    /**
     * @var array<string, array<mixed>>
     */
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

    /**
     * @param array<mixed> $dbiConfig
     */
    public function __construct(array $dbiConfig, ?\Closure $logCallback = null)
    {
        if ($logCallback) {
            $this->setLogCallback($logCallback);
        }
        if (!isset($dbiConfig['environment'])) {
            $dbiConfig['environment'] = APPLICATION_ENV;
        }
        $this->config = array_merge($dbiConfig, $dbiConfig['manager'] ?? []);
        $this->ignoreTables[] = self::$schemaInfoTable;
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
    public function setLogCallback(\Closure $callback): void
    {
        $this->__callback = $callback;
    }

    /**
     * Gets the available schema versions from the migration directory.
     *
     * @return array<Version> The available schema versions
     */
    public function getVersions(bool $appliedOnly = false): array
    {
        if (true === $appliedOnly && isset($this->appliedVersions)) {
            return $this->appliedVersions;
        }
        if (false === $appliedOnly && isset($this->versions)) {
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
            $this->versions[] = Version::loadFromFile($migrateDir.DIRECTORY_SEPARATOR.$file);
        }
        ksort($this->versions);
        if (true === $appliedOnly) {
            $appliedVersions = [];
            $this->getMissingVersions(null, $appliedVersions);
            $versions = $this->appliedVersions = array_filter($versions, function ($value, $key) use ($appliedVersions) {
                return in_array($key, $appliedVersions);
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $this->versions;
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
     * @param array<int> $appliedVersions
     *
     * @param-out array<int> $appliedVersions
     *
     * @return array<int>
     */
    public function getMissingVersions(?int $version = null, ?array &$appliedVersions = null): array
    {
        if (null === $version) {
            $version = $this->getLatestVersion();
        }
        $appliedVersions = [];
        if ($this->dbi->table(self::$schemaInfoTable)->exists()) {
            $appliedVersions = array_map('intval', $this->dbi->table(self::$schemaInfoTable)->fetchAllColumn('version'));
        }
        $versions = $this->getVersions();

        return array_filter(array_diff(array_keys($versions), $appliedVersions), function ($v) use ($version) {
            return $v <= $version;
        });
    }

    /**
     * Returns the version number of the latest schema version.
     */
    public function getLatestVersion(): ?int
    {
        $versions = $this->getVersions();
        end($versions);

        return key($versions);
    }

    /**
     * Boolean indicator for when the current schema version is the latest.
     */
    public function isLatest(): bool
    {
        if (($version = $this->getVersion()) === null) {
            return false;
        }

        return $this->getLatestVersion() === $this->getVersion();
    }

    /**
     * Boolean indicator for when there are migrations that have not been applied.
     */
    public function hasUpdates(): bool
    {
        return count($this->getMissingVersions()) > 0;
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
