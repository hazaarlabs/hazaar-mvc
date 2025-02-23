<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/SchemaManager.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI;

use Hazaar\Application;
use Hazaar\Application\FilePath;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Manager\Data;
use Hazaar\DBI\Manager\Schema;
use Hazaar\DBI\Manager\Snapshot;
use Hazaar\DBI\Manager\Version;
use Hazaar\Loader;
use Hazaar\Timer;

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

    private \Closure $logHandler;

    /**
     * @param array<mixed> $dbiConfig
     */
    public function __construct(array $dbiConfig, ?\Closure $logHandler = null)
    {
        if ($logHandler) {
            $this->logHandler = $logHandler;
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
     * Retrieves the database adapter instance.
     *
     * If the adapter is not already set, it will establish a connection first.
     *
     * @return Adapter the database adapter instance
     */
    public function getAdapter(): Adapter
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }

        return $this->dbi;
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

    /**
     * Retrieves a specific version from the internal collection of versions.
     *
     * This method checks if the requested version key exists in the versions array,
     * returning the associated Version instance if found, or null otherwise.
     *
     * @param int $version The version number to look up
     *
     * @return null|Version The located Version instance or null if not found
     */
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

    /**
     * Retrieves an applied version from the schema information table based on the provided version number.
     *
     * If the database connection is not established, it attempts to connect first.
     * Once connected, it searches for the matching version. If no version is found,
     * it returns null; otherwise, it returns the corresponding Version model.
     *
     * @param int $version version number to look up
     *
     * @return null|Version a Version instance if found, otherwise null
     */
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

    /**
     * Retrieves a schema based on the specified versions.
     *
     * Determines whether to use all schema versions or only the applied
     * versions, returning a loaded Schema object. If no versions exist,
     * null is returned.
     *
     * @param bool $allSchema flag to retrieve all available schema versions or only the applied versions
     *
     * @return null|Schema loaded Schema object or null when no versions are found
     */
    public function getSchema(bool $allSchema = false): ?Schema
    {
        $versions = $allSchema ? $this->getVersions() : $this->getAppliedVersions();
        if (0 === count($versions)) {
            return null;
        }

        return Schema::load($versions);
    }

    /**
     * Database migration method.
     *
     * This method does some fancy database migration magic. It makes use of the 'db' subdirectory in the project directory
     * which should contain the at least a `migrate` directory that contains schema migrations.
     *
     * A few things can occur here.
     *
     * # If the database schema does not exist, then a new schema will be created using the current schema.  The current
     * schema is resolved in-memory by loading all the migration files in the `migrate` directory and applying them in
     * order.  This will create the database at the latest version of the schema.
     * # If the database schema already exists, then the function will search for missing versions between the current
     * schema version and the latest schema version.  If no versions are missing, then the function will return false.
     * # If versions are missing, then the function will replay all the missing versions in order to bring the database
     * schema up to the `$version` parameter. If no version is requested ($version is NULL) then the latest version number is used.
     * # If the version numbers are different, then a migration will be performed.
     * # # If the requested version is greater than the current version, the migration mode will be 'up'.
     * # # If the requested version is less than the current version, the migration mode will be 'down'.
     * # All migration files between the two selected versions (current and requested) will be replayed using the migration mode.
     *
     * This process can be used to bring a database schema up to the latest version using database migration files stored in the
     * `db/migrate` project subdirectory. These migration files are typically created using the `\Hazaar\DBI\Manager::snapshot()`
     * method although they can be created manually.
     *
     * The migration is performed in a database transaction (if the database supports it) so that if anything goes wrong there
     * is no damage to the database. If something goes wrong, errors will be available in the migration log accessible with
     * `\Hazaar\DBI\Manager::getMigrationLog()`. Errors in the migration files can be fixed and the migration retried.
     *
     * @param null|int $version The target version to migrate to. If null, migrates to the latest version.
     *
     * @return bool returns true if the migration was successful, false otherwise
     */
    public function migrate(?int $version = null): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $timer = new Timer();
        $this->dbi->log('Starting migration...');
        $versions = $this->getMissingVersions();
        if (0 === count($versions)) {
            $this->dbi->log('No updates available.');

            return false;
        }
        $currentVersion = $this->getCurrentVersion();
        /*
         * If there is no current schema applied, then we need to create the schema from scratch
         * by replaying the migrations in-memory.  This prevents unnecessary database changes, such
         * as tables being created and then dropped.
         */
        if (null === $currentVersion) {
            $this->dbi->log('No schema found.  Creating initial schema.');

            try {
                $this->dbi->begin();
                $result = $this->getSchema(true)->toMigration()->replay($this->dbi);
                if (!$result) {
                    return false;
                }
                $schemaInfoTable = $this->dbi->table(self::$schemaInfoTable);
                foreach ($versions as $version) {
                    $schemaInfoTable->insert($version);
                }
                $this->dbi->log('Schema applied at version '.$version->number);
                $this->dbi->commit();
            } catch (\Exception $e) {
                $this->dbi->cancel();
                $this->dbi->log($e->getMessage());

                return false;
            }
        } else {
            $this->dbi->log('Found '.count($versions).' updates to apply.');
            foreach ($versions as $version) {
                $this->dbi->log('Replaying version '.$version->number.': '.$version->comment);
                if (!$version->replay($this->dbi, self::$schemaInfoTable)) {
                    return false;
                }
            }
        }
        $this->dbi->log('Migration completed in '.$timer);

        return true;
    }

    /**
     * Undo a migration version.
     *
     * This method rolls back a specific migration version. It first checks if the database
     * interface (DBI) is connected, and if not, it establishes a connection. Then, it retrieves
     * the applied version of the migration. If the version has not been applied, it logs a message
     * and skips the rollback. Otherwise, it performs the rollback using the DBI.
     *
     * @param int $versionNumber the version number of the migration to be rolled back
     *
     * @return bool returns true if the rollback was successful, false otherwise
     */
    public function rollback(int $versionNumber): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $version = $this->getAppliedVersion($versionNumber);
        if (null === $version) {
            $this->dbi->log("Version {$versionNumber} has not been applied.  Skipping rollback.");

            return false;
        }

        return $this->rollbackVersion($version);
    }

    public function rollbackVersion(Version $version): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $timer = new Timer();
        $result = $version->rollback($this->dbi, self::$schemaInfoTable);
        if ($result) {
            $this->dbi->log("Rollback of version {$version->number} completed in ".$timer);
        }

        return $result;
    }

    /**
     * Replays the specified version of the database changes.
     *
     * This method ensures that the database connection is established,
     * retrieves the specified version, rolls back the changes of that version,
     * and then replays the changes.
     *
     * @param int $version the version number to replay
     *
     * @return bool returns true if the replay was successful, false otherwise
     */
    public function replay(int $version): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $timer = new Timer();
        $version = $this->getVersion($version);
        $version->rollback($this->dbi, self::$schemaInfoTable);
        $result = $version->replay($this->dbi, self::$schemaInfoTable);
        if ($result) {
            $this->dbi->log("Replay of version {$version->number} completed in ".$timer);
        }

        return $result;
    }

    /**
     * Snapshot the database schema and create a new schema version with migration replay files.
     *
     * This method is used to create the database schema migration files. These files are used by
     * the \Hazaar\Adapter::migrate() method to bring a database up to a certain version. Using this method
     * simplifies creating these migration files and removes the need to create them manually when there are
     * trivial changes.
     *
     * Current supported database objects and operations are:
     *
     * | Operation | CREATE | ALTER | DROP | RENAME |
     * |-----------|--------|-------|------|--------|
     * | Extension |   X    |       |   X  |        |
     * | Table     |   X    |   X   |   X  |   X    |
     * | Constraint|   X    |       |   X  |        |
     * | Index     |   X    |       |   X  |        |
     * | View      |   X    |   X   |   X  |        |
     * | Function  |   X    |   X   |   X  |        |
     * | Trigger   |   X    |   X   |   X  |        |
     * |-----------|--------|-------|------|--------|
     *
     * !!! notice
     *
     * Table rename detection works by comparing new tables with removed tables for tables that have the same
     * columns. Because of this, rename detection will not work if columns are added or removed at the same time
     * the table is renamed. If you want to rename a table, make sure that this is the only operation being
     * performed on the table for a single snapshot. Modifying other tables will not affect this. If you want to
     * rename a table AND change it's column layout, make sure you do either the rename or the modifications
     * first, then snapshot, then do the other operation before snapshotting again.
     *
     * @param null|string $comment optional comment for the snapshot
     * @param bool        $test    if true, the method will only test for changes without saving the snapshot
     *
     * @return bool returns true if the snapshot was created and saved successfully, false otherwise
     */
    public function snapshot(?string $comment = null, bool $test = false): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $timer = new Timer();
        $missingVersions = $this->getMissingVersions();
        if (count($missingVersions) > 0) {
            $this->dbi->log('There are missing versions.  Please migrate the database before taking a snapshot.');

            return false;
        }
        $this->dbi->log('Importing database schema');
        $databaseSchema = Schema::import($this->dbi);
        $this->dbi->log('Loading master schema');
        $masterSchema = $this->getSchema(true);
        $snapshot = Snapshot::create($comment ?? 'Snapshot');
        if (null === $masterSchema) {
            $this->dbi->log('No master schema found. Creating initial snapshot.');
            $snapshot->setSchema($databaseSchema);
        } else {
            $this->dbi->log('Comparing schemas');
            $snapshot->compare($masterSchema, $databaseSchema);
        }
        if (0 === $snapshot->count()) {
            $this->dbi->log('No changes detected in '.$timer);

            return false;
        }
        // dump($snapshot->migration->down->actions[0]->toArray());
        $this->dbi->log('Found '.$snapshot->count().' changes to apply');
        if (true === $test) {
            return true;
        }
        $this->dbi->log('Setting version to '.$snapshot->version->number);
        $result = $snapshot->save($this->dbi, self::$schemaInfoTable, $this->migrateDir);
        if (!$result) {
            $this->dbi->log('Snapshot failed in '.$timer);

            return false;
        }
        $this->dbi->log('Snapshot completed in '.$timer);

        return true;
    }

    /**
     * @param array<mixed> $dataSchema
     */
    public function sync(?array $dataSchema = null, bool $force = false): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $timer = new Timer();
        $this->dbi->log('Initialising DBI data sync');
        $env = $this->config['environment'];
        $this->dbi->log('APPLICATION_ENV: '.$env);
        $dataFile = $this->workDir.DIRECTORY_SEPARATOR.'data.json';

        try {
            $sync = $dataSchema ? new Data($dataSchema) : Data::load($dataFile);
            $application = new Application($env);
            $syncHash = $sync->getHash();
            $syncHashFile = $application->getRuntimePath('.dbi_sync_hash');
            if (true !== $force
                && file_exists($syncHashFile)
                && $syncHash == trim(file_get_contents($syncHashFile))) {
                $this->dbi->log('Sync hash is unchanged.  Skipping data sync.');

                return true;
            }
            $currentVersion = $this->getCurrentVersion();
            if ($currentVersion) {
                $sync->appliedVersions = array_keys($this->getAppliedVersions());
                $this->dbi->log('Starting DBI data sync on schema version '.$currentVersion);
            }
            $this->dbi->begin();
            $result = $sync->run($this->dbi);
            if (false === $result) {
                throw new \Exception('Data sync failed');
            }
            $this->dbi->commit();
            if ($this->dbi->can('repair')) {
                $this->dbi->log('Running '.$this->dbi->getDriverName().' repair process');
                $result = $this->dbi->repair();
                $this->dbi->log('Repair '.($result ? 'completed successfully' : 'failed'));
            }
            if (false === file_exists($syncHashFile) || is_writable($syncHashFile)) {
                file_put_contents($syncHashFile, $syncHash);
            }
        } catch (\Throwable $e) {
            $this->dbi->log('DBI data sync error: '.$e->getMessage());
            $this->dbi->cancel();

            return false;
        }
        $this->dbi->log('DBI data sync completed in '.$timer);

        return true;
    }

    /**
     * Creates a checkpoint in the database.
     *
     * This method creates a checkpoint by taking a snapshot of the current master schema
     * and saving it. It also truncates the schema info table and inserts the checkpoint version.
     *
     * @param null|string $comment an optional comment for the checkpoint
     *
     * @return bool returns true if the checkpoint was successfully created, false otherwise
     */
    public function checkpoint(?string $comment = null): bool
    {
        if (!isset($this->dbi)) {
            $this->connect();
        }
        $masterSchema = $this->getSchema(true);
        if (null === $masterSchema) {
            $this->dbi->log('No master schema found.  Skipping checkpoint.');

            return false;
        }
        if (!count($this->versions) > 0) {
            $this->dbi->log('FATAL ERROR: No versions found.  Skipping checkpoint.');

            return false;
        }
        $timer = new Timer();
        $this->dbi->log('Creating checkpoint');
        $snapshot = Snapshot::create($comment ?? 'Checkpoint');
        $snapshot->setSchema($masterSchema);
        $result = $snapshot->save($this->dbi, self::$schemaInfoTable, $this->migrateDir);
        if (!$result) {
            return false;
        }
        foreach ($this->versions as $version) {
            $version->unlink();
        }
        $this->dbi->log('Truncating schema info table');
        $schemaTable = $this->dbi->table(self::$schemaInfoTable);
        $schemaTable->truncate();
        $this->dbi->log('Inserting checkpoint version');
        $schemaTable->insert($snapshot->getVersion());
        $this->dbi->log('Checkpoint completed in '.$timer);

        return true;
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
        $timer = new Timer();
        $this->dbi->log('WARNING: Deleting all database objects!');
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
                $this->dbi->log('Got stuck trying to resolve drop dependencies. Aborting!');

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
        $result = $this->createSchemaVersionTable();
        $this->dbi->log('All database objects deleted in '.$timer);
        $env = $this->config['environment'];
        $application = new Application($env);
        $syncHashFile = $application->getRuntimePath('.dbi_sync_hash');
        if (file_exists($syncHashFile)) {
            unlink($syncHashFile);
        }

        return $result;
    }

    /**
     * Returns the migration log.
     *
     * Snapshots and migrations are complex processes where many things happen in a single execution. This means stuff
     * can go wrong and you will probably want to know what/why/when they do.
     *
     * When running `\Hazaar\Adapter::snapshot()` or `\Hazaar\Adapter::migrate()` a log of what has been done is stored internally
     * in an array of timestamped messages. You can use the `\Hazaar\Adapter::getMigrationLog()` method to retrieve this
     * log so that if anything goes wrong, you can see what and fix it/
     *
     * @return array<LogEntry>
     */
    public function getMigrationLog(): array
    {
        return isset($this->dbi) ? $this->dbi->getEventLog() : [];
    }

    public function registerLogHandler(\Closure $handler): void
    {
        $this->logHandler = $handler;
        if (isset($this->dbi)) {
            $this->dbi->registerLogHandler($handler);
        }
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
            $this->dbi = new Adapter($config);
            if (isset($this->logHandler)) {
                $this->dbi->registerLogHandler($this->logHandler);
            }
            $this->dbi->log("Connected to database '{$this->config['dbname']}' on host '{$config['host']}'");
        } catch (ConnectionFailed $e) {
            if (7 !== $e->getCode() || true !== ake($config, 'createDatabase')) {
                throw $e;
            }
            $this->dbi->log('Database does not exist.  Attempting to create it as requested.');
            $config['dbname'] = isset($config['maintenanceDatabase'])
                ? $config['maintenanceDatabase']
                : $config['user'];
            $this->dbi->log("Connecting to database '{$config['dbname']}' on host '{$config['host']}'");
            $maintDB = new Adapter($config);
            $this->dbi->log("Creating database '{$config['dbname']}'");
            $maintDB->createDatabase($config['dbname']);
            $this->dbi->log("Retrying connection to database '{$config['dbname']}' on host '{$config['host']}'");
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
                $this->dbi->log('Failed to create schema info table.  Retrying...');
                $this->dbi->createSchema($config['schema']);
            }
        }

        return false;
    }

    /**
     * Creates the schema version table if it does not already exist.
     *
     * This method checks if the schema version table exists in the database.
     * If it does not exist, it creates the table with the following columns:
     * - number: A bigint that serves as the primary key.
     * - applied_on: A timestamp with time zone that defaults to the current timestamp.
     * - comment: A text field that can be null.
     * - migrate: A jsonb field that can be null.
     *
     * @return bool returns false if the table already exists, true if the table was created successfully
     */
    private function createSchemaVersionTable(): bool
    {
        if ($this->dbi->tableExists(self::$schemaInfoTable)) {
            return false;
        }
        $this->dbi->log('Creating schema info table');

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
            'comment' => [
                'type' => 'text',
                'not_null' => false,
            ],
            'migrate' => [
                'type' => 'jsonb',
                'null' => true,
            ],
        ]);
    }
}
