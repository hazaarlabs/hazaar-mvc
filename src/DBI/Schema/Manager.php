<?php

declare(strict_types=1);

/**
 * @file        Hazaar/DBI/SchemaManager.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2018 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\DBI\Schema;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\Application\FilePath;
use Hazaar\DBI\Adapter;
use Hazaar\DBI\DataMapper;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Result;
use Hazaar\DBI\Schema\Exception\Datasync;
use Hazaar\DBI\Schema\Exception\Schema;
use Hazaar\File;
use Hazaar\Loader;

/**
 * Relational Database Schema Manager.
 */
class Manager
{
    public const MACRO_VARIABLE = 1;
    public const MACRO_LOOKUP = 2;
    public static string $schemaInfoTable = 'schema_info';
    private Adapter $dbi;

    /**
     * @var array<mixed>
     */
    private array $dbiConfig;
    private string $dbDir;
    private string $migrateDir;
    private string $dataFile;

    /**
     * @var array<array<mixed|string>>
     */
    private array $migrationLog = [];

    /**
     * @var array<string>
     */
    private array $ignoreTables = ['hz_file', 'hz_file_chunk'];

    /**
     * @var array<int,array<string>>
     */
    private ?array $versions = null;
    private ?int $currentVersion = null;

    /**
     * @var array<int,string>
     */
    private ?array $appliedVersions = null;
    private ?\Closure $__callback = null;

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
        $this->dbiConfig = $dbiConfig;
        if (!isset($this->dbiConfig['environment'])) {
            $this->dbiConfig['environment'] = APPLICATION_ENV;
        }
        $managerConfig = array_merge($this->dbiConfig, $this->dbiConfig['manager'] ?? []);
        $this->ignoreTables[] = self::$schemaInfoTable;

        try {
            $this->log("Connecting to database '{$managerConfig['dbname']}' on host '{$managerConfig['host']}'");
            $this->dbi = new Adapter($managerConfig);
        } catch (ConnectionFailed $e) {
            if (true !== ake($managerConfig, 'createDatabase')) {
                throw $e;
            }
            $this->log('Database does not exist.  Attempting to create it as requested.');
            $managerConfig['orgDBName'] = $managerConfig['dbname'];
            $managerConfig['dbname'] = isset($managerConfig['maintenanceDatabase'])
                ? $managerConfig['maintenanceDatabase']
                : $managerConfig['user'];
            $this->log("Connecting to database '{$managerConfig['dbname']}' on host '{$managerConfig['host']}'");
            $maintDB = new Adapter($managerConfig);
            $this->log("Creating database '{$managerConfig['dbname']}'");
            $maintDB->createDatabase($managerConfig['orgDBName']);
            unset($maintDB);
            $managerConfig['dbname'] = $managerConfig['orgDBName'];
            unset($managerConfig['orgDBName']);
            $this->log("Retrying connection to database '{$managerConfig['dbname']}' on host '{$managerConfig['host']}'");
            $this->dbi = new Adapter($managerConfig);
        }
        $this->dbDir = $this->getSchemaManagerDirectory();
        $this->migrateDir = $this->dbDir.DIRECTORY_SEPARATOR.'migrate';
        $this->dataFile = $this->dbDir.DIRECTORY_SEPARATOR.'data.json';
    }

    /**
     * Returns the currently applied schema version.
     */
    public function getVersion(): ?int
    {
        if (null !== $this->currentVersion) {
            return $this->currentVersion;
        }
        if (false === $this->dbi->table(self::$schemaInfoTable)->exists()) {
            return null;
        }
        $result = $this->dbi->table(self::$schemaInfoTable)->findOne([], ['version' => 'max(version)']);

        return $this->currentVersion = (int) ake($result, 'version', false);
    }

    /**
     * Returns a list of available schema versions.
     *
     * @return array<int, string>
     */
    public function &getVersions(bool $returnFullPath = false, bool $appliedOnly = false): array
    {
        if (null === $this->versions) {
            $this->versions = [0 => [], 1 => []];
            // Get a list of all the available versions
            if (file_exists($this->migrateDir) && is_dir($this->migrateDir)) {
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
                    $version = (int) str_pad($matches[1], 14, '0', STR_PAD_RIGHT);
                    $this->versions[0][$version] = $this->migrateDir.DIRECTORY_SEPARATOR.$file;
                    $this->versions[1][$version] = str_replace('_', ' ', $matches[2]);
                }
                ksort($this->versions[0]);
                ksort($this->versions[1]);
            }
        }
        $versions = $this->versions ? ($returnFullPath ? $this->versions[0] : $this->versions[1]) : [];
        if (true === $appliedOnly) {
            if (null !== $this->appliedVersions) {
                return $this->appliedVersions;
            }
            $this->getMissingVersions(null, $appliedVersions);
            $versions = $this->appliedVersions = array_filter($versions, function ($value, $key) use ($appliedVersions) {
                return in_array($key, $appliedVersions);
            }, ARRAY_FILTER_USE_BOTH);
        }

        return $versions;
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
     * @return array<mixed>|false
     * */
    public function getSchema(?int $maxVersion = null): array|false
    {
        $schema = ['version' => 0];
        foreach (self::$tableMap as $i) {
            $schema[$i[0]] = [];
        }

        /**
         * Get a list of all the available versions.
         */
        $versions = $this->getVersions(true);
        foreach ($versions as $version => $file) {
            if (null !== $maxVersion && $version > $maxVersion) {
                break;
            }
            if (!($fileContent = @file_get_contents($file))) {
                throw new \Exception('Error reading schema migration file: '.$file);
            }
            if (!($migrate = json_decode($fileContent, true))) {
                throw new \Exception('Error decoding schema migration file: '.$file);
            }
            if (!array_key_exists('up', $migrate)) {
                continue;
            }
            foreach ($migrate['up'] as $level1 => $actions) {
                foreach ($actions as $level2 => $items) {
                    if (array_key_exists($level1, self::$tableMap)) {
                        $type = $level1;
                        $action = $level2;
                    } else {
                        $type = $level2;
                        $action = $level1;
                    }
                    if (!($map = ake(self::$tableMap, $type))) {
                        continue 2;
                    }
                    list($elem, $source, $contentType) = $map;
                    if (!array_key_exists($elem, $schema)) {
                        $schema[$elem] = [];
                    }
                    if (false !== $source) {
                        if ('alter' === $action) {
                            foreach ($items as $table => $alterations) {
                                if (true === $source) {
                                    $schema[$elem][$alterations['name']] = $alterations;
                                } else {
                                    foreach ($alterations as $altAction => $altColumns) {
                                        if ('drop' === $altAction) {
                                            if (!isset($schema['tables'][$table])) {
                                                throw new \Exception("Drop action on table '{$table}' which does not exist!");
                                            }
                                            // Remove the column from the table schema
                                            $schema['tables'][$table] = array_filter($schema['tables'][$table], function ($item) use ($altColumns) {
                                                return !in_array($item['name'], $altColumns);
                                            });
                                            // Update any constraints/indexes that reference this column
                                            if (isset($schema['constraints'])) {
                                                $schema['constraints'] = array_filter($schema['constraints'], function ($item) use ($altColumns) {
                                                    return !in_array($item['column'], $altColumns);
                                                });
                                            }
                                            if (isset($schema['indexes'])) {
                                                $schema['indexes'] = array_filter($schema['indexes'], function ($item) use ($table, $altColumns) {
                                                    return $item['table'] !== $table || 0 === count(array_intersect($item['columns'], $altColumns));
                                                });
                                            }
                                        } else {
                                            foreach ($altColumns as $colName => $colData) {
                                                if ('add' === $altAction) {
                                                    $schema['tables'][$table][] = $colData;
                                                } elseif ('alter' === $altAction && array_key_exists($table, $schema['tables'])) {
                                                    foreach ($schema['tables'][$table] as &$col) {
                                                        if ($col['name'] !== $colName) {
                                                            continue;
                                                        }
                                                        // If we are renaming the column, we need to update index and constraints
                                                        if (array_key_exists('name', $colData) && $col['name'] !== $colData['name']) {
                                                            if (isset($schema['constraints'])) {
                                                                array_walk($schema['constraints'], function (&$item) use ($colName, $colData) {
                                                                    if ($item['column'] === $colName) {
                                                                        $item['column'] = $colData['name'];
                                                                    }
                                                                });
                                                            }
                                                            if (isset($schema['indexes'])) {
                                                                array_walk($schema['indexes'], function (&$item) use ($colName, $colData) {
                                                                    if (in_array($colName, $item['columns'])) {
                                                                        $item['columns'][array_search($colName, $item['columns'])] = $colData['name'];
                                                                    }
                                                                });
                                                            }
                                                        }
                                                        // If the column data type is changing and there is no 'length' property, set the length to null.
                                                        if (array_key_exists('data_type', $colData)
                                                            && !array_key_exists('length', $colData)
                                                            && $col['data_type'] !== $colData['data_type']) {
                                                            $colData['length'] = null;
                                                        }
                                                        $col = array_merge($col, $colData);

                                                        break;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else {
                            foreach ($items as $item) {
                                if ('create' === $action) {
                                    $schema[$elem][$item['name']] = (true === $source) ? $item : $item[$source];
                                } elseif ('remove' === $action) {
                                    if (is_array($item)) {
                                        if (!array_key_exists('name', $item)) {
                                            throw new \Exception('Unable to remove elements with no name');
                                        }
                                        foreach ($schema[$elem] as $index => $childItem) {
                                            if (ake($childItem, 'name') === $item['name']
                                                && ake($childItem, 'table') === ake($item, 'table')) {
                                                unset($schema[$elem][$index]);
                                            }
                                        }
                                    } else {
                                        unset($schema[$elem][$item]);
                                        if ('table' === $type) {
                                            if (isset($schema['constraints'])) {
                                                $schema['constraints'] = array_filter($schema['constraints'], function ($c) use ($item) {
                                                    return $c['table'] !== $item;
                                                });
                                            }
                                            if (isset($schema['indexes'])) {
                                                $schema['indexes'] = array_filter($schema['indexes'], function ($i) use ($item) {
                                                    return $i['table'] !== $item;
                                                });
                                            }
                                        }
                                    }
                                } else {
                                    throw new Schema("I don't know how to handle: {$action}");
                                }
                            }
                        }
                    } else {
                        foreach ($items as $itemName => $item) {
                            if (is_string($item)) {
                                if ('create' === $action) {
                                    $schema[$elem][] = $item;
                                } elseif ('remove' === $action) {
                                    foreach ($schema[$elem] as $schemaItemName => &$schemaItem) {
                                        if (is_array($schemaItem)) {
                                            if (!array_key_exists($item, $schemaItem)) {
                                                continue;
                                            }
                                            unset($schemaItem[$item]);
                                        } elseif ($schemaItem !== $item) {
                                            continue;
                                        } else {
                                            unset($schema[$elem][$schemaItemName]);
                                        }

                                        break;
                                    }
                                }
                            // Functions removed are a bit different as we have to look at parameters.
                            } elseif ('function' === $type && 'remove' === $action) {
                                if (array_key_exists($itemName, $schema[$elem])) {
                                    foreach ($item as $params) {
                                        // Find the existing function and remove it
                                        foreach ($schema[$elem][$itemName] as $index => $func) {
                                            $cParams = array_map(function ($item) {
                                                return ake($item, 'type');
                                            }, ake($func, 'parameters'));
                                            // We do an array_diff_assoc so that parameter position is taken into account
                                            if (0 === count(array_diff_assoc($params, $cParams)) && 0 === count(array_diff_assoc($cParams, $params))) {
                                                unset($schema[$elem][$itemName][$index]);
                                            }
                                        }
                                    }
                                }
                            } elseif (array_key_exists('table', $item)) {
                                if ('create' === $action || 'alter' === $action) {
                                    $schema[$elem][$item['table']][$item['name']] = $item;
                                } elseif ('remove' === $action) {
                                    unset($schema[$elem][$item['table']][$item['name']]);
                                } else {
                                    throw new Schema("I don't know how to handle: {$action}");
                                }
                            } else {
                                if ('create' === $action || 'alter' === $action) {
                                    $name = $item['name'] ?? $itemName;
                                    $schema[$elem][$name][] = $item;
                                } else {
                                    throw new Schema("I don't know how to handle: {$action}");
                                }
                            }
                        }
                        $schema[$elem] = array_filter($schema[$elem], function ($item) {
                            return is_array($item) ? count($item) > 0 : true;
                        });
                    }
                    /*
                     * For types that have content, we need to add the version to the content if
                     * it is stored in an external file.
                     */
                    if ($contentType) {
                        foreach ($schema[$elem] as &$contentItem) {
                            if (true === $source) {
                                $this->processContent($version, $contentType, $contentItem);
                            } else {
                                foreach ($contentItem as &$contentGroup) {
                                    $this->processContent($version, $contentType, $contentGroup);
                                }
                            }
                        }
                    }
                }
            }
            $schema['version'] = $version;
        }
        if (0 === $schema['version']) {
            return false;
        }
        // Remove any empty stuff
        $schema = array_filter($schema, function ($item) {
            return !is_array($item) || count($item) > 0;
        });

        return $schema;
    }

    public function truncate(): void
    {
        foreach ($this->dbi->listTables() as $table) {
            $this->dbi->dropTable($table['schema'].'.'.$table['name'], true, true);
        }
    }

    /**
     * Snapshot the database schema and create a new schema version with migration replay files.
     *
     * This method is used to create the database schema migration files. These files are used by
     * the \Hazaar\Adapter::migrate() method to bring a database up to a certain version. Using this method
     * simplifies creating these migration files and removes the need to create them manually when there are
     * trivial changes.
     *
     * When developing your project
     *
     * Currently only the following changes are supported:
     * * Table creation, removal and rename.
     * * Column creation, removal and alteration.
     * * Index creation and removal.
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
     * @param string $comment         a comment to add to the migration file
     * @param bool   $test            Test only.  Does not make any changes.
     * @param int    $overrideVersion Manually specify the version number.  Default is to use current timestamp.
     *
     * @return bool True if the snapshot was successful. False if no changes were detected and nothing needed to be done.
     *
     * @throws Exception\Snapshot
     */
    public function snapshot(?string $comment = null, bool $test = false, ?int $overrideVersion = null)
    {
        $this->log('Snapshot process starting');
        if ($test) {
            $this->log('Test mode ENABLED');
        }
        $this->log('APPLICATION_ENV: '.$this->dbiConfig['environment']);
        if ($versions = $this->getVersions()) {
            end($versions);
            $latestVersion = key($versions);
        } else {
            $latestVersion = 0;
        }
        $version = $this->getVersion();
        if ($latestVersion > $version) {
            throw new Exception\Snapshot('Snapshoting a database that is not at the latest schema version is not supported.');
        }
        $this->dbi->begin();
        if (!is_dir($this->dbDir)) {
            if (file_exists($this->dbDir)) {
                throw new Exception\Snapshot('Unable to create database migration directory.  It exists but is not a directory!');
            }
            if (!is_writable(dirname($this->dbDir))) {
                throw new Exception\Snapshot('The directory that contains the database migration directory is not writable!');
            }
            mkdir($this->dbDir);
        } elseif (!\is_writable($this->dbDir)) {
            throw new Exception\Snapshot('Migration directory is not writable!');
        }

        try {
            $result = $this->dbi->query('SELECT CURRENT_TIMESTAMP');
            if (!$result instanceof Result) {
                throw new Exception\Snapshot('No rows returned!');
            }
            $this->log('Starting at: '.$result->fetchColumn(0));
        } catch (\Throwable $e) {
            $this->log('There was a problem connecting to the database!');

            throw $e;
        }
        $init = false;

        /**
         * Load the existing stored schema to use for comparison.
         */
        $schema = $this->getSchema();
        if ($schema) {
            $this->log('Existing schema loaded.');
            foreach (self::$tableMap as $item => $map) {
                $this->log(count(ake($schema, $map[0], [])).' '.$map[0].' defined');
            }
        } else {
            if (!$comment) {
                $comment = 'Initial Snapshot';
            }
            $this->log('No existing schema.  Creating initial snapshot.');
            $init = true;
            $schema = [];
        }
        if (!$comment) {
            $comment = 'New Snapshot';
        }
        $this->log('Comment: '.$comment);

        /**
         * Prepare a new version number based on the current date and time.
         */
        $version = $overrideVersion ?? date('YmdHis');

        /**
         * Stores the schema as it currently exists in the database.
         */
        $currentSchema = [
            'version' => $version,
            'extensions' => [],
            'tables' => [],
            'constraints' => [],
            'indexes' => [],
            'functions' => [],
            'views' => [],
            'triggers' => [],
        ];

        /**
         * Stores only changes between $schema and $currentSchema.  Here we define all possible elements
         * to ensure the correct ordering.  Later we remove all empty elements before saving the migration file.
         */
        $changes = [
            'up' => [
                'extension' => [],
                'sequence' => [
                    'create' => [],
                    'remove' => [],
                ],
                'table' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                    'rename' => [],
                ],
                'constraint' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'index' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'view' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'function' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'trigger' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
            ],
            'down' => [
                'raise' => [],
                'trigger' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'function' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'view' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'index' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'constraint' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                ],
                'table' => [
                    'create' => [],
                    'alter' => [],
                    'remove' => [],
                    'rename' => [],
                ],
                'sequence' => [
                    'create' => [],
                    'remove' => [],
                ],
                'extension' => [],
            ],
        ];
        if ($init) {
            $changes['down']['raise'] = 'Can not revert initial snapshot';
        }
        $this->log('*** SNAPSHOTTING EXTENSIONS ***');
        foreach ($this->dbi->listExtensions() as $extension) {
            $this->log("Processing extension '{$extension}'.");
            $currentSchema['extensions'][] = $extension;
            if (!(array_key_exists('extensions', $schema) && in_array($extension, $schema['extensions']))) {
                $this->log("+ Extension '{$extension}' has been created.");
                $changes['up']['extension']['create'][] = $extension;
                if (!$init) {
                    $changes['down']['extension']['remove'][] = $extension;
                }
            }
        }
        if (array_key_exists('extensions', $schema)) {
            /**
             * Now look for any extensions that have been removed.
             */
            $missing = array_diff($schema['extensions'], $currentSchema['extensions']);
            if (count($missing) > 0) {
                foreach ($missing as $extension) {
                    $this->log("- Extension '{$extension}' has been removed.");
                    $changes['up']['extension']['remove'][] = $extension;
                    $changes['down']['extension']['create'][] = $extension;
                }
            }
        }
        $this->log('*** SNAPSHOTTING SEQUENCES ***');
        foreach ($this->dbi->listSequences() as $sequenceName) {
            $this->log("Processing sequence '{$sequenceName}'.");
            $sequence = $this->dbi->describeSequence($sequenceName);
            foreach ($schema['tables'] as $table) {
                foreach ($table as $column) {
                    if (!('serial' === $column['type']
                        && $column['sequence'] === $sequenceName)) {
                        continue;
                    }

                    continue 3;
                }
            }
            unset($sequence['name']);
            $currentSchema['sequences'][$sequenceName] = $sequence;
            if (!(array_key_exists('sequences', $schema) && array_key_exists($sequenceName, $schema['sequences']))) {
                $this->log("+ Sequence '{$sequenceName}' has been created.");
                $changes['up']['sequence']['create'][$sequenceName] = $sequence;
                if (!$init) {
                    $changes['down']['sequence']['remove'][] = $sequenceName;
                }
            }
        }
        $this->log('*** SNAPSHOTTING TABLES ***');
        /*
         * Check for any new tables or changes to existing tables.
         * This pretty much looks just for tables to add and
         * any columns to alter.
         */
        foreach ($this->dbi->listTables() as $table) {
            $name = $table['name'];
            if (in_array($name, $this->ignoreTables)) {
                continue;
            }
            $this->log("Processing table '{$name}'.");
            if (!($cols = $this->dbi->describeTable($name, 'ordinal_position'))) {
                throw new Exception\Snapshot("Error getting table definition for table '{$name}'.  Does the connected user have the correct permissions?");
            }
            $currentSchema['tables'][$name] = $cols;
            // BEGIN PROCESSING TABLES
            if (array_key_exists('tables', $schema) && array_key_exists($name, $schema['tables'])) {
                $this->log("Table '{$name}' already exists.  Checking differences.");
                $diff = $this->getTableDiffs($cols, $schema['tables'][$name]);
                if (count($diff) > 0) {
                    $this->log("> Table '{$name}' has changed.");
                    $changes['up']['table']['alter'][$name] = $diff;
                    foreach ($diff as $diffMode => $colDiff) {
                        foreach ($colDiff as $colName => $colInfo) {
                            if ('drop' === $diffMode) {
                                $info = $this->getColumn($colInfo, $schema['tables'][$name]);
                                $changes['down']['table']['alter'][$name]['add'][$colName] = $info;
                            } elseif ('alter' == $diffMode) {
                                $info = $this->getColumn($colName, $schema['tables'][$name]);
                                $inverseDiff = array_intersect_key($info, $colInfo);
                                $changes['down']['table']['alter'][$name]['alter'][$colName] = $inverseDiff;
                            } elseif ('add' === $diffMode) {
                                $changes['down']['table']['alter'][$name]['drop'][] = $colName;
                            }
                        }
                    }
                } else {
                    $this->log("No changes to table '{$name}'.");
                }
            } else { // Table doesn't exist, so we add a command to create the whole thing
                $this->log("+ Table '{$name}' has been created.");
                $changes['up']['table']['create'][] = [
                    'name' => $name,
                    'cols' => $cols,
                ];
                if (!$init) {
                    $changes['down']['table']['remove'][] = $name;
                }
            } // END PROCESSING TABLES
        }
        if (array_key_exists('tables', $schema)) {
            /**
             * Now look for any tables that have been removed.
             */
            $missing = array_diff(array_keys($schema['tables']), array_keys($currentSchema['tables']));
            if (count($missing) > 0) {
                foreach ($missing as $table) {
                    $this->log("- Table '{$table}' has been removed.");
                    $changes['up']['table']['remove'][] = $table;
                    $changes['down']['table']['create'][] = [
                        'name' => $table,
                        'cols' => $schema['tables'][$table],
                    ];
                    // Add any constraints that were on this table to the down script so they get re-created
                    if (array_key_exists('constraints', $schema)
                        && array_key_exists($table, $schema['constraints'])) {
                        $changes['down']['constraint']['create'] = [];
                        foreach ($schema['constraints'][$table] as $constraintName => $constraint) {
                            $changes['down']['constraint']['create'][] = array_merge($constraint, [
                                'name' => $constraintName,
                                'table' => $table,
                            ]);
                        }
                    }
                    // Add any indexes that were on this table to the down script so they get re-created
                    if (array_key_exists('indexes', $schema)
                        && array_key_exists($table, $schema['indexes'])) {
                        $changes['down']['index']['create'] = [];
                        foreach ($schema['indexes'][$table] as $indexName => $index) {
                            $changes['down']['index']['create'][] = array_merge($index, [
                                'name' => $indexName,
                                'table' => $table,
                            ]);
                        }
                    }
                }
            }
        }
        // Now compare the create and remove changes to see if a table is actually being renamed
        if (true !== $init) {
            $this->log('Looking for renamed tables.');
            foreach ($changes['up']['table']['create'] as $createKey => $create) {
                foreach ($changes['up']['table']['remove'] as $removeKey => $remove) {
                    $diff = array_udiff($schema['tables'][$remove], $create['cols'], function ($a, $b) {
                        if ($a['name'] == $b['name']) {
                            return 0;
                        }

                        return ($a['name'] > $b['name']) ? 1 : -1;
                    });
                    if (!$diff) {
                        $this->log("> Table '{$remove}' has been renamed to '{$create['name']}'.");
                        $changes['up']['table']['rename'][] = [
                            'from' => $remove,
                            'to' => $create['name'],
                        ];
                        $changes['down']['table']['rename'][] = [
                            'from' => $create['name'],
                            'to' => $remove,
                        ];
                        // Clean up the changes
                        $changes['up']['table']['create'][$createKey] = null;
                        $changes['up']['table']['remove'][$removeKey] = null;
                        foreach ($changes['down']['table']['remove'] as $downRemoveKey => $downRemove) {
                            if ($downRemove === $create['name']) {
                                $changes['down']['table']['remove'][$downRemoveKey] = null;
                            }
                        }
                        foreach ($changes['down']['table']['create'] as $downCreateKey => $downCreate) {
                            if ($downCreate['name'] == $remove) {
                                $changes['down']['table']['create'][$downCreateKey] = null;
                            }
                        }
                    }
                }
            }
        }
        // Unset serial columns from the sequence list (PostgreSQL only)
        foreach ($changes['up']['table']['create'] as $createKey => $create) {
            foreach ($create['cols'] as $col) {
                if (!(array_key_exists('sequence', $col)
                && array_key_exists($col['sequence'], $changes['up']['sequence']['create'])
                && 'serial' === $col['type'])) {
                    continue;
                }
                unset($changes['up']['sequence']['create'][$col['sequence']]);
            }
        }
        $this->log('*** SNAPSHOTTING CONSTRAINTS ***');
        // BEGIN PROCESSING CONSTRAINTS
        $constraints = array_filter($this->dbi->listConstraints(), function ($item) {
            return !in_array($item['table'], $this->ignoreTables);
        });
        if (count($constraints) > 0) {
            $currentSchema['constraints'] = $constraints;
        }
        if (array_key_exists('constraints', $schema)) {
            $this->log('Looking for new constraints.');
            // Look for new constraints
            foreach ($constraints as $constraintName => $constraint) {
                if (!array_key_exists($constraintName, $schema['constraints'])) {
                    $this->log("+ Added new constraint '{$constraintName}'.");
                    $changes['up']['constraint']['create'][] = array_merge([
                        'name' => $constraintName,
                    ], $constraint);
                    // If the constraint was added at the same time as the table, we don't need to add the removes
                    if (!$init && !in_array($constraint['table'], $changes['down']['table']['remove'])) {
                        $changes['down']['constraint']['remove'][] = ['name' => $constraintName, 'table' => $constraint['table']];
                    }
                }
            }
            $this->log('Looking for removed constraints');
            // Look for any removed constraints.  If there are no constraints in the current schema, then all have been removed.
            $missing = array_diff(array_keys($schema['constraints']), array_keys($currentSchema['constraints']));
            if (count($missing) > 0) {
                foreach ($missing as $constraintName) {
                    $this->log("- Constraint '{$constraintName}' has been removed.");
                    $idef = $schema['constraints'][$constraintName];
                    $changes['up']['constraint']['remove'][] = $constraintName;
                    $changes['down']['constraint']['create'][] = array_merge([
                        'name' => $constraintName,
                    ], $idef);
                }
            }
        } elseif (count($constraints) > 0) {
            foreach ($constraints as $constraintName => $constraint) {
                $this->log("+ Added new constraint '{$constraintName}'.");
                $changes['up']['constraint']['create'][] = array_merge([
                    'name' => $constraintName,
                ], $constraint);
                if (!$init) {
                    $changes['down']['constraint']['remove'][] = ['name' => $constraintName, 'table' => $constraint['table']];
                }
            }
        } // END PROCESSING CONSTRAINTS
        $this->log('*** SNAPSHOTTING INDEXES ***');
        // BEGIN PROCESSING INDEXES
        $indexes = array_filter($this->dbi->listIndexes(), function ($item) {
            return !in_array($item['table'], $this->ignoreTables);
        });
        if (count($indexes) > 0) {
            foreach ($indexes as $indexName => $index) {
                // Check if the index is actually a constraint
                if (array_key_exists($indexName, $currentSchema['constraints'])) {
                    continue;
                }
                $currentSchema['indexes'][$indexName] = $index;
            }
        }
        if (array_key_exists('indexes', $schema)) {
            $this->log('Looking for new indexes.');
            // Look for new indexes
            foreach ($indexes as $indexName => $index) {
                // Check if the index is actually a constraint
                if (array_key_exists($indexName, $currentSchema['constraints'])) {
                    continue;
                }
                if (array_key_exists($indexName, $schema['indexes'])) {
                    continue;
                }
                $this->log("+ Added new index '{$indexName}'.");
                $changes['up']['index']['create'][] = array_merge([
                    'name' => $indexName,
                ], $index);
                if (!$init) {
                    $changes['down']['index']['remove'][] = $indexName;
                }
            }
            $this->log('Looking for removed indexes');
            // Look for any removed indexes.  If there are no indexes in the current schema, then all have been removed.
            $missing = array_diff(array_keys($schema['indexes']), array_keys($currentSchema['indexes']));
            if (count($missing) > 0) {
                foreach ($missing as $indexName) {
                    $this->log("- Index '{$indexName}' has been removed.");
                    $idef = $schema['indexes'][$indexName];
                    $changes['up']['index']['remove'][] = $indexName;
                    $changes['down']['index']['create'][] = array_merge([
                        'name' => $indexName,
                    ], $idef);
                }
            }
        } elseif (count($indexes) > 0) {
            foreach ($indexes as $indexName => $index) {
                // Check if the index is actually a constraint
                if (array_key_exists($indexName, $currentSchema['constraints'])) {
                    continue;
                }
                $this->log("+ Added new index '{$indexName}'.");
                $changes['up']['index']['create'][] = array_merge([
                    'name' => $indexName,
                ], $index);
                if (!$init) {
                    $changes['down']['index']['remove'][] = $indexName;
                }
            }
        } // END PROCESSING INDEXES
        $this->log('*** SNAPSHOTTING VIEWS ***');
        // BEGIN PROCESSING VIEWS
        foreach ($this->dbi->listViews() as $view) {
            $name = $view['name'];
            $this->log("Processing view '{$name}'.");
            if (!($info = $this->dbi->describeView($name))) {
                throw new Exception\Snapshot("Error getting view definition for view '{$name}'.  Does the connected user have the correct permissions?");
            }
            $currentSchema['views'][$name] = $info;
            if (array_key_exists('views', $schema) && array_key_exists($name, $schema['views'])) {
                $this->log("View '{$name}' already exists.  Checking differences.");
                $diff = array_diff_assoc($schema['views'][$name], $info);
                if (count($diff) > 0) {
                    $this->log("> View '{$name}' has changed.");
                    $changes['up']['view']['alter'][$name] = $info;
                    $changes['down']['view']['alter'][$name] = $schema['views'][$name];
                } else {
                    $this->log("No changes to view '{$name}'.");
                }
            } else { // View doesn't exist, so we add a command to create the whole thing
                $this->log("+ View '{$name}' has been created.");
                $changes['up']['view']['create'][] = $info;
                if (!$init) {
                    $changes['down']['view']['remove'][] = $name;
                }
            }
        }
        if (array_key_exists('views', $schema)) {
            $missing = array_diff(array_keys($schema['views']), array_keys($currentSchema['views']));
            if (count($missing) > 0) {
                foreach ($missing as $view) {
                    $this->log("- View '{$view}' has been removed.");
                    $changes['up']['view']['remove'][] = $view;
                    $changes['down']['view']['create'][] = $schema['views'][$view];
                }
            }
        }
        // END PROCESSING VIEWS
        $this->log('*** SNAPSHOTTING FUNCTIONS ***');
        // BEGIN PROCESSING FUNCTIONS
        foreach ($this->dbi->listFunctions() as $name) {
            $this->log("Processing function '{$name}'.");
            if (!($func = $this->dbi->describeFunction($name))) {
                throw new Exception\Snapshot("Error getting function definition for functions '{$name}'.  Does the connected user have the correct permissions?");
            }
            foreach ($func as $info) {
                if (true === ake($this->dbiConfig, 'manager.functionsInFiles', true)) {
                    $functions[$info['name']] = $info['content'];
                    unset($info['content']);
                }
                $currentSchema['functions'][$name][] = $info;
                $params = [];
                foreach ($info['parameters'] as $p) {
                    $params[] = $p['type'];
                }
                $fullname = $name.'('.implode(', ', $params).')';
                if (array_key_exists('functions', $schema)
                    && array_key_exists($name, $schema['functions'])
                    && count($exInfo = array_filter($schema['functions'][$name], function ($item) use ($info) {
                        if (!array_key_exists('parameters', $item)) {
                            if (!array_key_exists('parameters', $info) || 0 === count($info['parameters'])) {
                                return true;
                            }
                            $item['parameters'] = [];
                        }
                        if (count($item['parameters']) !== count($info['parameters'])) {
                            return false;
                        }
                        foreach ($item['parameters'] as $i => $p) {
                            if (!(array_key_exists($i, $info['parameters']) && $info['parameters'][$i]['type'] === $p['type'])) {
                                return false;
                            }
                        }

                        return true;
                    })) > 0) {
                    $this->log("Function '{$fullname}' already exists.  Checking differences.");
                    foreach ($exInfo as $e) {
                        if (!array_key_exists('parameters', $e)) {
                            $e['parameters'] = [];
                        }
                        $diff = array_diff_assoc_recursive($info, $e);
                        if (count($diff) > 0) {
                            $this->log("> Function '{$fullname}' has changed.");
                            $changes['up']['function']['alter'][] = $info;
                            $changes['down']['function']['alter'][] = $e;
                        } else {
                            $this->log("No changes to function '{$fullname}'.");
                        }
                    }
                } else { // View doesn't exist, so we add a command to create the whole thing
                    $this->log("+ Function '{$fullname}' has been created.");
                    $changes['up']['function']['create'][] = $info;
                    if (!$init) {
                        $changes['down']['function']['remove'][] = ['name' => $name, 'parameters' => $params];
                    }
                }
            }
        }
        if (array_key_exists('functions', $schema)) {
            $missing = [];
            foreach ($schema['functions'] as $funcName => $funcInstances) {
                $missingFunc = null;
                foreach ($funcInstances as $func) {
                    if (array_key_exists($funcName, $currentSchema['functions'])
                        && count($currentSchema['functions']) > 0) {
                        $p1 = ake($func, 'parameters', []);
                        foreach ($currentSchema['functions'][$funcName] as $cFunc) {
                            $p2 = ake($cFunc, 'parameters', []);
                            if (0 === count(array_diff_assoc_recursive($p1, $p2))
                                && 0 === count(array_diff_assoc_recursive($p2, $p1))) {
                                continue 2;
                            }
                        }
                    }
                    if (!array_key_exists($funcName, $missing)) {
                        $missing[$funcName] = [];
                    }
                    $missing[$funcName][] = $func;
                }
            }
            if (count($missing) > 0) {
                foreach ($missing as $funcName => $funcInstances) {
                    foreach ($funcInstances as $func) {
                        $params = [];
                        foreach (ake($func, 'parameters', []) as $param) {
                            $params[] = $param['type'];
                        }
                        $funcFullName = $funcName.'('.implode(', ', $params).')';
                        $this->log("- Function '{$funcFullName}' has been removed.");
                        $changes['up']['function']['remove'][$funcName][] = $params;
                        if (!array_key_exists($funcName, $changes['down']['function']['create'])) {
                            $changes['down']['function']['create'][$funcName] = [];
                        }
                        $changes['down']['function']['create'][] = $func;
                    }
                }
            }
        }
        // END PROCESSING FUNCTIONS
        $this->log('*** SNAPSHOTTING TRIGGERS ***');
        // BEGIN PROCESSING TRIGGERS
        foreach ($this->dbi->listTriggers() as $trigger) {
            $name = $trigger['name'];
            $this->log("Processing trigger '{$name}'.");
            if (!($info = $this->dbi->describeTrigger($trigger['name']))) {
                throw new Exception\Snapshot("Error getting trigger definition for '{$name}'.  Does the connected user have the correct permissions?");
            }
            if (true === ake($this->dbiConfig, 'manager.functionsInFiles', true)) {
                $functions[$info['name']] = $info['content'];
                unset($info['content']);
            }
            $currentSchema['triggers'][$name] = $info;
            if (array_key_exists('triggers', $schema) && array_key_exists($name, $schema['triggers'])) {
                $this->log("Trigger '{$name}' already exists.  Checking differences.");
                $diff = array_diff_assoc_recursive($schema['triggers'][$name], $info);
                if (count($diff) > 0) {
                    $this->log("> Trigger '{$name}' has changed.");
                    $changes['up']['trigger']['alter'][$name] = $info;
                    $changes['down']['trigger']['alter'][$name] = $schema['triggers'][$name];
                } else {
                    $this->log("No changes to trigger '{$name}'.");
                }
            } else {
                $this->log("+ Trigger '{$name}' has been created on table '{$info['table']}'.");
                $changes['up']['trigger']['create'][] = $info;
                if (!$init) {
                    $changes['down']['trigger']['remove'][] = ['name' => $name, 'table' => $info['table']];
                }
            }
        }
        if (array_key_exists('triggers', $schema)) {
            $missing = array_diff(array_keys($schema['triggers']), array_keys($currentSchema['triggers']));
            if (count($missing) > 0) {
                foreach ($missing as $trigger) {
                    $this->log("- Trigger '{$trigger}' has been removed.");
                    $changes['up']['trigger']['remove'][] = ['name' => $trigger, 'table' => $schema['triggers'][$trigger]['table']];
                    $changes['down']['trigger']['create'][] = [
                        'name' => $trigger,
                        'cols' => $schema['triggers'][$trigger],
                    ];
                }
            }
        }

        // END PROCESSING TRIGGERS
        try {
            $this->log('*** SNAPSHOT SUMMARY ***');
            array_remove_empty($changes);
            // If there are no changes, bail out now
            if (!(count(ake($changes, 'up', [])) + count(ake($changes, 'down', []))) > 0) {
                $this->log('No changes detected.');
                $this->dbi->cancel();

                return false;
            }
            if (array_key_exists('up', $changes)) {
                $tokens = ['create' => '+', 'alter' => '>', 'remove' => '-'];
                foreach ($changes['up'] as $type => $methods) {
                    foreach ($methods as $method => $actions) {
                        $this->log($tokens[$method].' '.ucfirst($method).' '.$type.' count: '.count($actions));
                    }
                }
            }
            // If we are testing, then return the diff between the previous schema version
            if ($test) {
                $this->dbi->cancel();

                return ake($changes, 'up');
            }
            // Save the migrate diff file
            if (!file_exists($this->migrateDir)) {
                $this->log('Migration directory does not exist.  Creating.');
                mkdir($this->migrateDir);
            }
            $migrateFile = $this->migrateDir.'/'.$version.'_'.preg_replace('/[^A-Za-z0-9]/', '_', trim($comment)).'.json';
            $this->log("Writing migration file to '{$migrateFile}'");
            file_put_contents($migrateFile, json_encode($changes, JSON_PRETTY_PRINT));
            if (!empty($functions)) {
                if (!file_exists($this->migrateDir.'/functions')) {
                    mkdir($this->migrateDir.'/functions');
                }
                if (!file_exists($this->migrateDir.'/functions/'.$version)) {
                    mkdir($this->migrateDir.'/functions/'.$version);
                }
                foreach ($functions as $name => $content) {
                    $funcFile = $this->migrateDir.'/functions/'.$version.'/'.$name.'.sql';
                    $this->log("Writing function file to '{$funcFile}'");
                    file_put_contents($funcFile, $content);
                }
            }
            // Merge in static schema elements (like data) and save the current schema file
            if ($data = ake($schema, 'data')) {
                $this->log('Merging schema data records into current schema');
                $currentSchema['data'] = $data;
            }
            $this->createInfoTable();
            $this->dbi->insert(self::$schemaInfoTable, [
                'version' => $version,
            ]);
            $this->dbi->commit();
        } catch (\Throwable $e) {
            $this->log('Aborting: '.$e->getMessage());

            return false;
        }

        return true;
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
     * Database migration method.
     *
     * This method does some fancy database migration magic. It makes use of the 'db' subdirectory in the project directory
     * which should contain the schema.json file. This file is the current database schema definition.
     *
     * A few things can occur here.
     *
     * # If the database schema does not exist, then a new schema will be created using the schema.json schema definition file.
     * This will create the database at the latest version of the schema.
     * # If the database schema already exists, then the current version is checked against the version requested using the
     * $version parameter. If no version is requested ($version is NULL) then the latest version number is used.
     * # If the version numbers are different, then a migration will be performed.
     * # # If the requested version is greater than the current version, the migration mode will be 'up'.
     * # # If the requested version is less than the current version, the migration mode will be 'down'.
     * # All migration files between the two selected versions (current and requested) will be replayed using the migration mode.
     *
     * This process can be used to bring a database schema up to the latest version using database migration files stored in the
     * db/migrate project subdirectory. These migration files are typically created using the \Hazaar\Adapter::snapshot() method
     * although they can be created manually. Take care when using manually created migration files.
     *
     * The migration is performed in a database transaction (if the database supports it) so that if anything goes wrong there
     * is no damage to the database. If something goes wrong, errors will be availabl in the migration log accessible with
     * \Hazaar\Adapter::getMigrationLog(). Errors in the migration files can be fixed and the migration retried.
     *
     * @param int $version the database schema version to migrate to
     *
     * @return bool Returns true on successful migration. False if no migration was neccessary. Throws an Exception on error.
     */
    public function migrate(
        ?int $version = null,
        bool $forceDataSync = false,
        bool $test = false,
        bool $keepTables = false,
        bool $forceReinitialise = false
    ): bool {
        $this->log('Migration process starting');
        if ($test) {
            $this->log('Test mode ENABLED');
        }
        $this->log('APPLICATION_ENV: '.$this->dbiConfig['environment']);
        $mode = 'up';
        $currentVersion = 0;
        $versions = $this->getVersions(true);
        $latestVersion = $this->getLatestVersion();
        if ($version) {
            $version = (int) str_pad((string) $version, 14, '0', STR_PAD_RIGHT);
            // Make sure the requested version exists
            if (!array_key_exists($version, $versions)) {
                throw new Exception\Migration("Unable to find migration version '{$version}'.");
            }
        } else {
            if (count($versions) > 0) {
                // No version supplied so we grab the last version
                end($versions);
                $version = key($versions);
                reset($versions);
                $this->log('Migrating database to version: '.$version);
            } else {
                $version = $latestVersion;
                $this->log('Initialising database at version: '.$version);
            }
        }

        // Check that the database exists and can be written to.
        try {
            if (!$this->dbi->schemaExists()) {
                $this->log('Database does not exist.  Creating...');
                $this->dbi->createSchema();
                if (($dbiUser = $this->dbiConfig['user'] ?? '') && $this->dbi->config['user'] !== $dbiUser) {
                    $schemaName = $this->dbi->getSchemaName();
                    $this->dbi->grant('USAGE', 'SCHEMA '.$schemaName, $dbiUser);
                }
            }
            $this->createInfoTable();
        } catch (\PDOException $e) {
            if (7 == $e->getCode()) {
                throw new Exception\Migration('Database does not exist.');
            }

            throw $e;
        }
        // Get the current version (if any) from the database
        if ($this->dbi->table(self::$schemaInfoTable)->exists()) {
            $result = $this->dbi->table(self::$schemaInfoTable)->find([], ['version'])->order('version', SORT_DESC);
            if ($row = $result->fetch()) {
                $currentVersion = $row['version'];
                $this->log('Current database version: '.($currentVersion ? $currentVersion : 'None'));
            }
        }
        $users = [];
        if (true === ake($this->dbi->config, 'createUser') && isset($this->dbiConfig['user'])) {
            $users[] = [
                'name' => $this->dbiConfig['user'],
                'password' => $this->dbiConfig['password'],
                'privileges' => ['LOGIN'],
            ];
        }
        if (isset($this->dbi->config['users'])) {
            $users = array_merge($users, $this->dbi->config['users']->toArray());
        }
        if (count($users) > 0) {
            $this->createUserIfNotExists($users);
        }
        if (true === $forceReinitialise) {
            $this->log('WARNING: Forcing full database re-initialisation.  THIS WILL DELETE ALL DATA!!!');
            $this->log('IF YOU DO NOT WANT TO DO THIS, YOU HAVE 10 SECONDS TO CANCEL');
            sleep(10);
            $this->log('DELETING YOUR DATA!!!  YOU WERE WARNED!!!');
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
                exit('Something really BAD happened!');
            }
            $functions = $this->dbi->listFunctions(true);
            foreach ($functions as $function) {
                $this->dbi->dropFunction($function['name'], $function['parameters'], true);
            }
            $extensions = $this->dbi->listExtensions();
            foreach ($extensions as $extension) {
                $this->dbi->dropExtension($extension);
            }
            $currentVersion = 0;
            $this->createInfoTable();
        }
        $this->log('Starting database migration process.');
        if (0 === $currentVersion && $version === $latestVersion) {
            /**
             * This section sets up the database using the existing schema without migration replay.
             *
             * The criteria here is:
             *
             * * No current version
             * * $version must equal the schema file version
             *
             * Otherwise we have to replay the migration files from current version to the target version.
             */
            if (!($schema = $this->getSchema($version))) {
                throw new Exception\Migration('This application has no schema file.  Database schema is not being managed.');
            }
            if (!array_key_exists('version', $schema)) {
                $schema['version'] = 1;
            }
            $tables = array_filter($this->dbi->listTables(), function ($value) {
                return !($value['schema'] === $this->dbi->getSchemaName() && in_array($value['name'], $this->ignoreTables));
            });
            if (count($tables) > 0 && true !== $keepTables) {
                throw new Exception\Migration('Tables exist in database but no schema info was found!  This should only be run on an empty database!');
            }
            // There is no current database so just initialise from the schema file.
            $this->log('Initialising database'.($version ? " at version '{$version}'" : ''));
            if ($schema['version'] > 0) {
                if ($this->applySchema($schema, $test, $keepTables)) {
                    $missingVersions = $this->getMissingVersions($version);
                    foreach ($missingVersions as $ver) {
                        $this->dbi->insert(self::$schemaInfoTable, ['version' => $ver]);
                    }
                }
            }
            $forceDataSync = true;
        } else {
            $this->log("Migrating to version '{$version}'.");
            // Compare known versions with the versions applied to the database and get a list of missing versions less than the requested version
            $missingVersions = $this->getMissingVersions($version, $appliedVersions);
            if (($count = count($missingVersions)) > 0) {
                $this->log("Found {$count} missing versions that will get replayed.");
            }
            $migrations = array_combine($missingVersions, array_fill(0, count($missingVersions), 'up'));
            ksort($migrations);
            if ($version < $currentVersion) {
                $downMigrations = [];
                foreach ($versions as $ver => $info) {
                    if ($ver > $version && $ver <= $currentVersion && in_array($ver, $appliedVersions)) {
                        $downMigrations[$ver] = 'down';
                    }
                }
                krsort($downMigrations);
                $migrations = $migrations + $downMigrations;
            }
            if (0 === count($migrations)) {
                $this->log('Nothing to do!');
            } else {
                $globalVars = null;
                foreach ($migrations as $ver => $mode) {
                    $source = $versions[$ver];
                    if ('up' == $mode) {
                        $this->log("--> Replaying version '{$ver}' from file '{$source}'.");
                    } elseif ('down' == $mode) {
                        $this->log("<-- Rolling back version '{$ver}' from file '{$source}'.");
                    } else {
                        throw new Exception\Migration('Unknown migration mode!');
                    }
                    if (!($currentSchema = json_decode(file_get_contents($source), true))) {
                        throw new Exception\Migration('Unable to parse the migration file.  Bad JSON?');
                    }
                    if (!array_key_exists($mode, $currentSchema)) {
                        continue;
                    }

                    try {
                        $this->dbi->begin();
                        if (true !== $this->replay($currentSchema[$mode], $test, $globalVars, $ver)) {
                            $this->dbi->cancel();

                            return false;
                        }
                        if ('up' == $mode) {
                            $this->log('Inserting version record: '.$ver);
                            if (!$test) {
                                $this->dbi->insert(self::$schemaInfoTable, ['version' => $ver]);
                            }
                        } elseif ('down' == $mode) {
                            $this->log('Removing version record: '.$ver);
                            if (!$test) {
                                $this->dbi->delete(self::$schemaInfoTable, ['version' => $ver]);
                            }
                        }
                        if ($this->dbi->errorCode() > 0) {
                            throw new Exception\Migration($this->dbi->errorInfo()[2]);
                        }
                        $this->dbi->commit();
                    } catch (\Throwable $e) {
                        $this->dbi->cancel();

                        throw $e;
                    }
                    $this->log("-- Replay of version '{$ver}' completed.");
                } while ($source = next($versions));
                if ('up' === $mode && $version === $latestVersion) {
                    $forceDataSync = true;
                }
            }
        }
        if (!$test) {
            $this->initDBIFilesystem();
        }
        // Insert data records.  Will only happen in an up migration.
        if (!$this->syncData(null, $test, $forceDataSync)) {
            return false;
        }
        $this->log('Migration completed successfully.');

        return true;
    }

    public function migrateReplay(int $version, bool $test = false): bool
    {
        $this->log('Replaying version: '.$version);
        $versions = $this->getVersions(true, true);
        if (!array_key_exists($version, $versions)) {
            throw new \Exception('Version '.$version.' is not an applied migration.  You may only replay applied migrations.');
        }
        $currentSchema = json_decode(file_get_contents($versions[$version]), true);
        if (!(array_key_exists('down', $currentSchema) && array_key_exists('up', $currentSchema))) {
            throw new \Exception('Migration file does not contain both an "up" and "down" script.');
        }

        try {
            $globalVars = null;
            $this->dbi->begin();
            $this->log('Removing version: '.$version);
            if (!$this->replay($currentSchema['down'], $test, $globalVars, $version)) {
                throw new \Exception('Failed to migrate down!');
            }
            $this->log('Replaying version: '.$version);
            if (!$this->replay($currentSchema['up'], $test, $globalVars, $version)) {
                throw new \Exception('Failed to migrate down!');
            }
            $this->dbi->commit();
        } catch (\Throwable $e) {
            $this->dbi->cancel();

            throw $e;
        }
        $this->log('Migration completed successfully.');

        return true;
    }

    /**
     * Undo a migration version.
     *
     * @param array<int> $rollbacks
     */
    public function rollback(int $version, bool $test = false, array &$rollbacks = []): bool
    {
        $this->log('Rolling back version '.$version.($test ? ' in TEST MODE' : ''));
        $versions = $this->getVersions(true, true);
        if (!array_key_exists($version, $versions)) {
            $this->log('Version '.$version.' is not currently applied to the schema');

            return false;
        }
        $items = ake(json_decode(file_get_contents($versions[$version]), true), 'down');
        $versionList = array_keys($versions);
        sort($versionList);

        /**
         * LOOK FOR DEPENDENTS SO WE CAN ROLL BACK THOSE VERSIONS AS WELL.
         */
        $modifiedTables = array_merge(ake($items, 'table.remove', []), array_keys(ake($items, 'table.alter', [])));
        $start = array_search($version, $versionList);
        $dependents = [];
        for ($i = $start + 1; $i < count($versionList); ++$i) {
            $dependent = false;
            $compareVersion = $versionList[$i];
            if (!array_key_exists($compareVersion, $versions)) {
                throw new \Exception('Something weird happened:  List had version number that is not in version array!');
            }
            $versionItem = json_decode(file_get_contents($versions[$compareVersion]), true);
            if (!($compareItem = ake($versionItem, 'up'))) {
                continue;
            }
            // Migrations that alter a modified table
            if (($tableAlter = ake($compareItem, 'table.alter'))
                && count(array_intersect(array_keys($tableAlter), $modifiedTables)) > 0) {
                $dependent = true;
            }
            // Migrations that remove a modified table
            if (($tableRemove = ake($compareItem, 'table.remove'))
                && count(array_intersect($tableRemove, $modifiedTables)) > 0) {
                $dependent = true;
            }
            // Migrations that create constraints on or referencing a modified table
            if ($constraintCreate = ake($compareItem, 'constraint.create')) {
                foreach ($constraintCreate as $constraint) {
                    if ((($table = ake($constraint, 'table')) && false !== array_search($table, $modifiedTables))
                        || ($referencesTable = ake($constraint, 'references.table')) && false !== array_search($referencesTable, $modifiedTables)) {
                        $dependent = true;
                    }
                }
            }
            // Migrations that create constraints on or referencing a modified table
            if (ake($compareItem, 'constraint.remove')) {
                if (!is_array($constraintItems = ake($versionItem, 'down.constraint.create'))) {
                    continue;
                }
                foreach ($constraintItems as $constraint) {
                    if ((($table = ake($constraint, 'table')) && false !== array_search($table, $modifiedTables))
                        || ($referencesTable = ake($constraint, 'references.table')) && false !== array_search($referencesTable, $modifiedTables)) {
                        $dependent = true;
                    }
                }
            }
            // Migrations that create indexes on a modified table
            if ($indexCreate = ake($compareItem, 'index.create')) {
                foreach ($indexCreate as $index) {
                    if (false !== array_search(ake($index, 'table'), $modifiedTables)) {
                        $dependent = true;
                    }
                }
            }
            // Migrations that remove indexes on a modified table
            if (ake($compareItem, 'index.remove')) {
                if (!is_array($indexItems = ake($versionItem, 'down.index.create'))) {
                    continue;
                }
                foreach ($indexItems as $indexItem) {
                    if (false !== array_search(ake($indexItem, 'table'), $modifiedTables)) {
                        $dependent = true;
                    }
                }
            }
            if (true === $dependent) {
                $this->log('- '.$version.' depends on '.$compareVersion);
                $dependents[] = $compareVersion;
            }
        }
        rsort($dependents);
        foreach ($dependents as $dependent) {
            if (false !== array_search($dependent, $rollbacks)) {
                continue;
            }
            $this->rollback($dependent, $test, $rollbacks);
        }

        try {
            $this->dbi->begin();
            if ($this->replay($items, $test) === $test) {
                $this->dbi->cancel();

                return false;
            }
            if (!$test) {
                $this->log('Removing version record: '.$version);
                $this->dbi->delete(self::$schemaInfoTable, ['version' => $version]);
            }
            if ($this->dbi->errorCode() > 0) {
                throw new \Exception($this->dbi->errorInfo()[2]);
            }
            $this->dbi->commit();
        } catch (\Throwable $e) {
            $this->dbi->cancel();

            throw $e;
        }
        $rollbacks[] = $version;
        $this->log("Rollback of version '{$version}' completed.");

        return true;
    }

    /**
     * Takes a schema definition and creates it in the database.
     *
     * @param array<mixed> $schema
     */
    public function applySchema(array $schema, bool $test = false, bool $keepTables = false): bool
    {
        $this->dbi->begin();

        try {
            // Create Extensions
            if ($extensions = ake($schema, 'extensions')) {
                foreach ($extensions as $extension) {
                    $this->dbi->createExtension($extension);
                }
            }
            // Create tables
            if ($tables = ake($schema, 'tables')) {
                foreach ($tables as $table => $columns) {
                    if (true === $keepTables && $this->dbi->table($table)->exists()) {
                        $curColumns = $this->dbi->describeTable($table);
                        $diff = array_diff_assoc_recursive($curColumns, $columns);
                        if (count($diff) > 0) {
                            throw new Schema('Table "'.$table.'" already exists but is different.  Bailing out!');
                        }
                        $this->log('Table "'.$table.'" already exists and looks current.  Skipping.');

                        continue;
                    }
                    $ret = $this->dbi->createTable($table, $columns);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating table '.$table.': '.$this->dbi->errorInfo()[2]);
                    }
                    if (($dbiUser = $this->dbiConfig['user']) && $this->dbi->config['user'] !== $dbiUser) {
                        $this->dbi->grant(['INSERT', 'SELECT', 'UPDATE', 'DELETE'], $dbiUser, $table);
                    }
                }
            }
            // Create foreign keys
            if ($constraints = ake($schema, 'constraints')) {
                // Do primary keys first
                $curConstraints = $this->dbi->listConstraints();
                foreach ($constraints as $constraintName => $constraint) {
                    if ('PRIMARY KEY' !== $constraint['type']) {
                        continue;
                    }
                    if (true === $keepTables && array_key_exists($constraintName, $curConstraints)) {
                        $diff = array_diff_assoc_recursive($curConstraints[$constraintName], $constraint);
                        if (count($diff) > 0) {
                            throw new Schema('Constraint "'.$constraintName.'" already exists but is different.  Bailing out!');
                        }
                        $this->log('Constraint "'.$constraintName.'" already exists and looks current.  Skipping.');

                        continue;
                    }
                    $ret = $this->dbi->addConstraint($constraintName, $constraint);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating constraint '.$constraintName.': '.$this->dbi->errorInfo()[2]);
                    }
                }
                // Now do all other constraints
                foreach ($constraints as $constraintName => $constraint) {
                    if ('PRIMARY KEY' === $constraint['type']) {
                        continue;
                    }
                    if (true === $keepTables && array_key_exists($constraintName, $curConstraints)) {
                        $diff = array_diff_assoc_recursive($curConstraints[$constraintName], $constraint);
                        if (count($diff) > 0) {
                            throw new Schema('Constraint "'.$constraintName.'" already exists but is different.  Bailing out!');
                        }
                        $this->log('Constraint "'.$constraintName.'" already exists and looks current.  Skipping.');

                        continue;
                    }
                    $ret = $this->dbi->addConstraint($constraintName, $constraint);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating constraint '.$constraintName.': '.$this->dbi->errorInfo()[2]);
                    }
                }
            }
            // Create indexes
            if ($indexes = ake($schema, 'indexes')) {
                foreach ($indexes as $indexName => $indexInfo) {
                    $ret = $this->dbi->createIndex($indexName, $indexInfo['table'], $indexInfo);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating index '.$indexName.': '.$this->dbi->errorInfo()[2]);
                    }
                }
            }
            // Create views
            if ($views = ake($schema, 'views')) {
                foreach ($views as $view => $info) {
                    if (true === $keepTables && $this->dbi->viewExists($view)) {
                        $curInfo = $this->dbi->describeView($view);
                        $diff = array_diff_assoc_recursive($curInfo, $info);
                        if (count($diff) > 0) {
                            throw new Schema('View "'.$view.'" already exists but is different.  Bailing out!');
                        }
                        $this->log('View "'.$view.'" already exists and looks current.  Skipping.');

                        continue;
                    }
                    $ret = $this->dbi->createView($view, $info['content']);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating view '.$view.': '.$this->dbi->errorInfo()[2]);
                    }
                    if (($dbiUser = $this->dbiConfig['user']) && $this->dbi->config['user'] !== $dbiUser) {
                        $this->dbi->grant(['SELECT'], $dbiUser, $view);
                    }
                }
            }
            // Create functions
            if ($functions = ake($schema, 'functions')) {
                foreach ($functions as $items) {
                    foreach ($items as $info) {
                        $params = [];
                        if (array_key_exists('parameters', $info)) {
                            foreach ($info['parameters'] as $p) {
                                $params[] = $p['type'];
                            }
                        }
                        $ret = $this->dbi->createFunction($info['name'], $info);
                        if (!$ret || $this->dbi->errorCode() > 0) {
                            throw new Schema('Error creating function '.$info['name'].'('.implode(', ', $params).'): '.$this->dbi->errorInfo()[2]);
                        }
                    }
                }
            }
            // Create triggers
            if ($triggers = ake($schema, 'triggers')) {
                $curTriggers = [];
                foreach ($triggers as $name => $info) {
                    if (true === $keepTables) {
                        if (!array_key_exists($info['table'], $curTriggers)) {
                            $curTriggers[$info['table']] = array_collate($this->dbi->listTriggers($info['table']), 'name');
                        }
                        if (array_key_exists($info['name'], $curTriggers[$info['table']])) {
                            $curInfo = $this->dbi->describeTrigger($info['name']);
                            $diff = array_diff_assoc_recursive($curInfo, $info);
                            if (count($diff) > 0) {
                                throw new Schema('Trigger "'.$info['name'].'" already exists but is different.  Bailing out!');
                            }
                            $this->log('Trigger "'.$info['name'].'" already exists and looks current.  Skipping.');

                            continue;
                        }
                    }
                    $ret = $this->dbi->createTrigger($info['name'], $info['table'], $info);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema("Error creating trigger '{$info['name']} on table '{$info['table']}': "
                            .$this->dbi->errorInfo()[2]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->dbi->cancel();

            throw $e;
        }
        if (true === $test) {
            $this->dbi->cancel();

            return false;
        }
        $this->dbi->commit();

        return true;
    }

    public function applySchemaFromFile(string $filename): bool
    {
        if (!$filename = realpath($filename)) {
            throw new Schema('Schema file not found!');
        }
        if (!($schema = json_decode(file_get_contents($filename), true))) {
            throw new Schema('Schema file contents is not a valid schema!');
        }

        return $this->applySchema($schema);
    }

    /**
     * @param array<mixed> $dataSchema
     */
    public function syncData(?array $dataSchema = null, bool $test = false, bool $forceDataSync = false): bool
    {
        $this->log('Initialising DBI data sync');
        $this->log('APPLICATION_ENV: '.$this->dbiConfig['environment']);
        if (null === $dataSchema) {
            $schema = $this->getSchema($this->getVersion());
            $dataSchema = array_key_exists('data', $schema) ? $schema['data'] : [];
            $this->loadDataFromFile($dataSchema, $this->dataFile);
        }
        if (0 === count($dataSchema)) {
            $this->log('No data schema to sync.  Skipping data sync.');

            return true;
        }
        $syncHash = md5(json_encode($dataSchema));
        $syncHashFile = Application::getInstance()->getRuntimePath('.dbi_sync_hash');
        if (true !== $forceDataSync
            && file_exists($syncHashFile)
            && $syncHash == trim(file_get_contents($syncHashFile))) {
            $this->log('Sync hash is unchanged.  Skipping data sync.');

            return true;
        }
        $this->log('Starting DBI data sync on schema version '.$this->getVersion());
        $this->currentVersion = null;  // Removed to force reload
        $this->appliedVersions = null; // Removed to force reload
        $this->dbi->begin();
        $globalVars = [];

        try {
            $records = [];
            foreach ($dataSchema as $info) {
                $this->processDataObject($info, $records, $globalVars);
            }
            if ($test) {
                $this->dbi->cancel();
            } else {
                $this->dbi->commit();
            }
            $this->log('DBI Data sync completed successfully!');
            if ($this->dbi->can('repair')) {
                $this->log('Running '.$this->dbi->getDriverName().' repair process');
                $result = $this->dbi->repair();
                $this->log('Repair '.($result ? 'completed successfully' : 'failed'));
            }
        } catch (\Throwable $e) {
            $this->dbi->cancel();
            $this->log('DBI Data sync error: '.$e->getMessage());
        }
        if (false === file_exists($syncHashFile) || is_writable($syncHashFile)) {
            file_put_contents($syncHashFile, $syncHash);
        }

        return true;
    }

    /**
     * Sets a callback function that can be used to process log messages.
     *
     * The callback function provided must accept two arguments.  $time and $msg.
     *
     * Example, to simply echo formatted log data:
     *
     * '''php
     * $manager->setLogCallback(function($time, $msg){
     *      echo date('Y-m-d H:i:s', (int)$time) . " - $msg\n";
     * });
     * '''
     *
     * @param \Closure $callback The 'callable' callback function.  See: is_callable();
     */
    public function setLogCallback(\Closure $callback): bool
    {
        $this->__callback = $callback;

        return true;
    }

    /**
     * Returns the migration log.
     *
     * Snapshots and migrations are complex processes where many things happen in a single execution. This means stuff
     * can go wrong and you will probably want to know what/why when they do.
     *
     * When running \Hazaar\Adapter::snapshot() or \Hazaar\Adapter::migrate() a log of what has been done is stored internally
     * in an array of timestamped messages. You can use the \Hazaar\Adapter::getMigrationLog() method to retrieve this
     * log so that if anything goes wrong, you can see what and fix it/
     *
     * @return array<int,array<mixed>>
     */
    public function getMigrationLog(): array
    {
        return $this->migrationLog;
    }

    /**
     * @param array<array{
     *  name:string,
     *  password:string,
     *  privileges:array<string>|string
     * }>|string $userOrUsers
     */
    public function createUserIfNotExists(array|string $userOrUsers): void
    {
        $users = is_array($userOrUsers) ? $userOrUsers : ['name' => $userOrUsers];
        $currentUsers = array_merge($this->dbi->listUsers(), $this->dbi->listGroups());
        foreach ($users as $user) {
            if (in_array($user['name'], $currentUsers)) {
                continue;
            }
            $this->log('Creating role: '.$user['name']);
            $privileges = ['INHERIT'];
            if (array_key_exists('privileges', $user)) {
                if (is_array($user['privileges'])) {
                    $privileges = array_merge($privileges, $user['privileges']);
                } else {
                    $privileges[] = $user['privileges'];
                }
            }
            if (!$this->dbi->createUser($user['name'], $user['password'], $privileges)) {
                throw new \Exception("Error creating role '{$user['name']}': ".ake($this->dbi->errorInfo(), 2));
            }
        }
    }

    public function checkpoint(?int $version = null): bool
    {
        if (!$version) {
            $version = $this->getLatestVersion();
        }
        if (file_exists($this->migrateDir) && is_dir($this->migrateDir)) {
            $dir = dir($this->migrateDir);
            while ($file = $dir->read()) {
                if ('.' === substr($file, 0, 1)) {
                    continue;
                }
                unlink($this->migrateDir.DIRECTORY_SEPARATOR.$file);
            }
        }
        $this->versions = [];
        $this->dbi->table(self::$schemaInfoTable)->truncate();

        return $this->snapshot('CHECKPOINT', false, $version);
    }

    public function registerOutputHandler(callable $callback): void
    {
        $this->__callback = $callback;
    }

    /**
     * Creates the info table that stores the version info of the current database.
     */
    private function createInfoTable(): bool
    {
        if (!$this->dbi->table(Manager::$schemaInfoTable)->exists()) {
            $this->dbi->createTable(Manager::$schemaInfoTable, [
                'version' => [
                    'data_type' => 'int8',
                    'not_null' => true,
                    'primarykey' => true,
                ],
            ]);

            return true;
        }

        return false;
    }

    /**
     * @param array<mixed> $haystack
     */
    private function getColumn(string $needle, array $haystack, string $key = 'name'): mixed
    {
        foreach ($haystack as $item) {
            if (array_key_exists($key, $item) && $item[$key] == $needle) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $haystack
     */
    private function colExists(string $needle, array $haystack, string $key = 'name'): bool
    {
        return (null !== $this->getColumn($needle, $haystack, $key)) ? true : false;
    }

    /**
     * @param array<mixed> $new
     * @param array<mixed> $old
     *
     * @return array<mixed>
     */
    private function getTableDiffs(array $new, array $old): array
    {
        $diff = [];
        // Look for any differences between the existing schema file and the current schema
        $this->log('Looking for new and updated columns');
        foreach ($new as $col) {
            // Check if the column is in the schema and if so, check it for changes
            if (($oldColumn = $this->getColumn($col['name'], $old)) !== null) {
                $columnDiff = [];
                foreach ($col as $key => $value) {
                    if ((array_key_exists($key, $oldColumn) && $value !== $oldColumn[$key])
                    || (!array_key_exists($key, $oldColumn) && null !== $value)) {
                        $columnDiff[$key] = $value;
                    }
                }
                if (count($columnDiff) > 0) {
                    $this->log("> Column '{$col['name']}' has changed");
                    $diff['alter'][$col['name']] = $columnDiff;
                }
            } else {
                $this->log("+ Column '{$col['name']}' is new.");
                $diff['add'][$col['name']] = $col;
            }
        }
        $this->log('Looking for removed columns');
        foreach ($old as $col) {
            if (!$this->colExists($col['name'], $new)) {
                $this->log("- Column '{$col['name']}' has been removed.");
                $diff['drop'][] = $col['name'];
            }
        }

        return $diff;
    }

    /**
     * @param array<string> $item
     */
    private function processContent(int $version, string $type, array &$item): void
    {
        if (array_key_exists('content', $item) || !array_key_exists('name', $item)) {
            return;
        }
        $sourceFile = $this->migrateDir
            .DIRECTORY_SEPARATOR.$type
            .DIRECTORY_SEPARATOR.(string) $version
            .DIRECTORY_SEPARATOR.$item['name'].'.sql';
        if (file_exists($sourceFile) && ($content = file_get_contents($sourceFile))) {
            $item['content'] = $content;
        }
    }

    /**
     * Reply a database migration schema file.
     *
     * This should only be used internally by the migrate method to replay an individual schema migration file.
     *
     * @param array<mixed>      $schema     The JSON decoded schema to replay
     * @param bool              $test       If true, the migration will be simulated but not actually executed
     * @param null|array<mixed> $globalVars
     */
    private function replay(
        array $schema,
        bool $test = false,
        ?array &$globalVars = null,
        ?int $version = null
    ): bool {
        foreach ($schema as $level1 => $data) {
            switch ($level1) {
                case 'data':
                    if (true === $test) {
                        continue 2;
                    }
                    if (!is_array($data)) {
                        $data = [$data];
                    }
                    $this->log('Processing '.count($data).' data sync items');
                    // Sneaky conversion from array to stdClass if needed
                    $data = json_decode(json_encode($data));
                    $records = [];
                    foreach ($data as $dataItem) {
                        $this->processDataObject($dataItem, $records, $globalVars);
                    }
                    $this->log('Finished processing data sync items');

                    break;

                case 'exec':
                    if (true === $test) {
                        continue 2;
                    }
                    if (!is_array($data)) {
                        $data = [$data];
                    }
                    foreach ($data as $execItem) {
                        $this->log('Executing SQL: '.$execItem);
                        if (false === $this->dbi->exec($execItem)) {
                            $this->log(ake($this->dbi->errorInfo(), 2));

                            return false;
                        }
                    }

                    break;

                default:
                    foreach ($data as $level2 => $items) {
                        if (array_key_exists($level1, self::$tableMap)) {
                            $this->replayItems($level1, $level2, $items, $test, true, $version);
                        } elseif (array_key_exists($level2, self::$tableMap)) {
                            $this->replayItems($level2, $level1, $items, $test, true, $version);
                        } else {
                            throw new Exception\Migration('Unsupported schema migration module: '.$level1);
                        }
                    }
            }
        }

        return !$test;
    }

    /**
     * @param array<mixed> $items
     */
    private function replayItems(
        string $type,
        string $action,
        array $items,
        bool $test = false,
        bool $primaryKeysFirst = true,
        ?int $version = null
    ): bool {
        // Replay primary key constraints first!
        if ('constraint' === $type && 'create' === $action && true === $primaryKeysFirst) {
            $pkItems = array_filter($items, function ($i) {
                return 'PRIMARY KEY' === $i['type'];
            });
            $this->replayItems($type, $action, $pkItems, $test, false, $version);
            $items = array_filter($items, function ($i) {
                return 'PRIMARY KEY' !== $i['type'];
            });
        }
        foreach ($items as $itemName => $item) {
            switch ($action) {
                case 'create':
                case 'add':
                    if ('extension' === $type) {
                        $this->log("+ Creating extension '{$item}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->createExtension($item);
                    } elseif ('table' === $type) {
                        $this->log("+ Creating table '{$item['name']}'.");
                        if (true === $test) {
                            break;
                        }
                        $this->dbi->createTable($item['name'], $item['cols']);
                        if (($dbiUser = $this->dbiConfig['user']) && $dbiUser != $this->dbi->config['user']) {
                            $this->dbi->grant(['INSERT', 'SELECT', 'UPDATE', 'DELETE'], $dbiUser, $item['name']);
                        }
                    } elseif ('index' === $type) {
                        $this->log("+ Creating index '{$item['name']}' on table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->createIndex($item['name'], $item['table'], ['columns' => $item['columns'], 'unique' => $item['unique']]);
                    } elseif ('constraint' === $type) {
                        $this->log("+ Creating constraint '{$item['name']}' on table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->addConstraint($item['name'], $item);
                        if (isset($item['data'])) {
                            $dataObject = (object) [
                                'table' => $item['table'],
                                'rows' => $item['data'],
                            ];
                            $this->processDataObject($dataObject);
                        }
                    } elseif ('view' === $type) {
                        $this->log("+ Creating view '{$item['name']}'.");
                        if ($test) {
                            break;
                        }
                        $this->processContent($version, 'views', $item);
                        $this->dbi->createView($item['name'], $item['content']);
                        if (($dbiUser = $this->dbiConfig['user']) && $dbiUser != $this->dbi->config['user']) {
                            $this->dbi->grant(['SELECT'], $dbiUser, $item['name']);
                        }
                    } elseif ('function' === $type) {
                        $params = [];
                        if (array_key_exists('parameters', $item)) {
                            foreach ($item['parameters'] as $p) {
                                $params[] = $p['type'];
                            }
                        }
                        $this->log("+ Creating function '{$item['name']}(".implode(', ', $params).').');
                        if ($test) {
                            break;
                        }
                        $this->processContent($version, 'functions', $item);
                        $this->dbi->createFunction($item['name'], $item);
                        if (true === ake($items, 'grant')
                            && ($dbiUser = $this->dbiConfig['user'])
                            && $dbiUser != $this->dbi->config['user']) {
                            $this->dbi->grant(['EXECUTE'], $dbiUser, 'FUNCTION '.$item['name']);
                        }
                    } elseif ('trigger' === $type) {
                        $this->log("+ Creating trigger '{$item['name']}' on table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->processContent($version, 'functions', $item);
                        $this->dbi->createTrigger($item['name'], $item['table'], $item);
                        if (true === ake($items, 'grant')
                            && ($dbiUser = $this->dbiConfig['user'])
                            && $dbiUser != $this->dbi->config['user']) {
                            $this->dbi->grant(['EXECUTE'], $dbiUser, 'FUNCTION '.$item['name']);
                        }
                    } else {
                        $this->log("I don't know how to create a {$type}!");
                    }

                    break;

                case 'drop':
                case 'remove':
                    if ('extension' === $type) {
                        $this->log("- Removing extension '{$item}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropExtension($item, true);
                    } elseif ('table' === $type) {
                        $this->log("- Removing table '{$item}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropTable($item, true, true);
                    } elseif ('constraint' === $type) {
                        if (!(is_array($item) && \array_key_exists('name', $item) && array_key_exists('table', $item))) {
                            throw new \Exception('Removing a constraint requires a constraint name and table name.');
                        }
                        $this->log("- Removing constraint '{$item['name']}' from table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropConstraint($item['name'], $item['table'], true, true);
                    } elseif ('index' === $type) {
                        $this->log("- Removing index '{$item}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropIndex($item, true);
                    } elseif ('view' === $type) {
                        $this->log("- Removing view '{$item}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropView($item, true, true);
                    } elseif ('function' === $type) {
                        $params = ake($item, 'parameters', []);
                        $this->log("- Removing function '{$item['name']}(".implode(', ', $params).').');
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropFunction($item['name'], $params, false, true);
                    } elseif ('trigger' === $type) {
                        $this->log("- Removing trigger '{$item['name']}' from table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropTrigger($item['name'], $item['table'], false, true);
                    } else {
                        $this->log("I don't know how to remove a {$type}!");
                    }

                    break;

                case 'alter':
                case 'change':
                    $this->log("> Altering {$type} {$itemName}");
                    if ('table' === $type) {
                        foreach ($item as $alterAction => $columns) {
                            foreach ($columns as $colName => $col) {
                                if ('add' == $alterAction) {
                                    $this->log("+ Adding column '{$col['name']}'.");
                                    if ($test) {
                                        break;
                                    }
                                    $this->dbi->addColumn($itemName, $col);
                                } elseif ('alter' == $alterAction) {
                                    $this->log("> Altering column '{$colName}'.");
                                    if ($test) {
                                        break;
                                    }
                                    $this->dbi->alterColumn($itemName, $colName, $col);
                                } elseif ('drop' == $alterAction) {
                                    $this->log("- Dropping column '{$col}'.");
                                    if ($test) {
                                        break;
                                    }
                                    $this->dbi->dropColumn($itemName, $col, true);
                                }
                                if ($this->dbi->errorCode() > 0) {
                                    throw new Exception\Migration($this->dbi->errorInfo()[2]);
                                }
                            }
                        }
                        if (($dbiUser = $this->dbiConfig['user']) && $dbiUser != $this->dbi->config['user']) {
                            $this->dbi->grant(['INSERT', 'SELECT', 'UPDATE', 'DELETE'], $dbiUser, $itemName);
                        }
                    } elseif ('view' === $type) {
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropView($itemName, false, true);
                        if ($this->dbi->errorCode() > 0) {
                            throw new Exception\Migration($this->dbi->errorInfo()[2]);
                        }
                        $this->dbi->createView($itemName, $item['content']);
                    } elseif ('function' === $type) {
                        $params = [];
                        if ($parameters = ake($item, 'parameters')) {
                            foreach ($parameters as $p) {
                                $params[] = $p['type'];
                            }
                        }
                        $this->log("+ Replacing function '{$item['name']}(".implode(', ', $params).').');
                        if ($test) {
                            break;
                        }
                        $this->dbi->createFunction($item['name'], $item);
                    } elseif ('trigger' === $type) {
                        $this->log("+ Replacing trigger '{$item['name']}' on table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropTrigger($item['name'], $item['table'], false, true);
                        $this->dbi->createTrigger($item['name'], $item['table'], $item);
                    } else {
                        $this->log("I don't know how to alter a {$type}!");
                    }

                    break;

                case 'rename':
                case 'move':
                    $this->log("> Renaming {$type} item: {$item['from']} => {$item['to']}");
                    if ($test) {
                        break;
                    }
                    if ('table' == $type) {
                        $this->dbi->renameTable($item['from'], $item['to']);
                    } else {
                        $this->log("I don't know how to rename a {$type}!");
                    }

                    break;

                default:
                    $this->log("I don't know how to {$action} a {$type}!");

                    break;
            }
            if ($this->dbi->errorCode() > 0) {
                throw new Exception\Migration(ake($this->dbi->errorInfo(), 2));
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $dataSchema
     */
    private function loadDataFromFile(array &$dataSchema, File|string $file, ?string $childElement = null): void
    {
        if (!$file instanceof File) {
            $file = new File($file);
        }
        if (!$file->exists()) {
            return;
        }
        if (!($data = $file->parseJSON())) {
            throw new Datasync("Unable to parse the DBI data file.  Bad JSON in {$file}");
        }
        if (null !== $childElement) {
            $data = ake($data, $childElement);
        }
        if (!is_array($data)) {
            return;
        }
        foreach ($data as &$item) {
            if (is_string($item)) {
                $this->loadDataFromFile($dataSchema, $file->dirname().DIRECTORY_SEPARATOR.ltrim($item, DIRECTORY_SEPARATOR));
            } else {
                $dataSchema[] = $item;
            }
        }
    }

    /**
     * @return array<mixed>|false
     */
    private function processMacro(string $field): array|false
    {
        if (!preg_match('/^\:\:(\w+)(\((\w+)\))?\:?(.*)$/', $field, $matches)) {
            return false;
        }
        if ($matches[2]) {
            $criteria = [];
            $parts = explode(',', $matches[4]);
            foreach ($parts as $part) {
                list($key, $value) = explode('=', $part, 2);
                $criteria[$key] = is_numeric($value) ? (int) $value : $value;
            }

            return [self::MACRO_LOOKUP, $matches[1], $matches[3], $criteria];
        }
        if ($matches[1]) {
            return [self::MACRO_VARIABLE, $matches[1]];
        }

        return false;
    }

    /**
     * @param array<mixed> $row     The row to prepare
     * @param array<mixed> $records The records that have been queried
     * @param array<mixed> $refs    The references to other tables
     * @param array<mixed> $vars    The variables to use in the row
     *
     * @return array<mixed>
     */
    private function prepareRow(
        array &$row,
        array &$records = [],
        ?array $refs = null,
        ?array $vars = null
    ): array {
        $rowRefs = [];
        if (count($refs) > 0) {
            $row = array_merge($row, $refs);
        }
        if (null === $vars) {
            $vars = new \stdClass();
        }
        // Find any macros that have been defined in record fields
        foreach ($row as $columnName => &$field) {
            if (!is_string($field)) {
                continue;
            }
            $field = match_replace($field, $vars);
            if (is_numeric($field)) {
                settype($field, 'integer');
            }
            if (($ref = $this->processMacro($field)) === false) {
                continue;
            }
            $rowRefs[$columnName] = $ref;
        }
        // Process any macros that have been defined in record fields
        foreach ($rowRefs as $columnName => $ref) {
            $found = false;
            $value = null;

            switch ($ref[0]) {
                case self::MACRO_VARIABLE:
                    if ($found = property_exists($vars, $ref[1])) {
                        $value = ake($vars, $ref[1]);
                    }

                    break;

                case self::MACRO_LOOKUP:
                    list($refType, $refTable, $refColumn, $refCriteria) = $ref;
                    // Lookup already queried data records
                    if (array_key_exists($refTable, $records)) {
                        foreach (get_object_vars($records[$refTable]) as $record) {
                            if (count(\array_diff_assoc($refCriteria, (array) $record)) > 0) {
                                continue;
                            }
                            $value = ake($record, $refColumn);
                            $found = true;

                            break;
                        }
                    } else { // Fallback SQL query
                        $match = $this->dbi->table($refTable)
                            ->limit(1)
                            ->find($refCriteria, ['value' => $refColumn])
                            ->fetch()
                        ;
                        if (false !== $match) {
                            $found = true;
                            $value = ake($match, 'value');
                        }
                    }

                    break;
            }
            if (false === $found) {
                throw new Datasync("Macro for column '{$columnName}' did not find a value.");
            }
            $row[$columnName] = $value;
        }

        return $row;
    }

    /**
     * @param array<mixed> $records
     * @param array<mixed> $globalVars
     */
    private function processDataObject(
        \stdClass $info,
        ?array &$records = null,
        ?array &$globalVars = null
    ): bool {
        if (($requiredVersion = ake($info, 'version')) && !array_key_exists((int) $requiredVersion, $this->getVersions(false, true))) {
            $this->log('Skipping section due to missing version '.$requiredVersion);

            return false;
        }
        if (!is_array($records)) {
            $records = [];
        }
        if (!is_array($env = ake($info, 'env', [$this->dbiConfig['environment']]))) {
            $env = [$env];
        }
        if (!in_array($this->dbiConfig['environment'], $env)) {
            return false;
        }
        // Set up any data object variables
        if (!isset($globalVars)) {
            $globalVars = [];
        }
        // We are setting these are global variables so store them un-prepared
        if (count($vars = ake($info, 'vars', new \stdClass())) > 0
            && !property_exists($info, 'table')) {
            $globalVars = array_merge($globalVars, $vars);
        }
        $vars = array_merge($globalVars, $vars);
        // Prepare variables  using the compiled list of variables
        if (count($vars) > 0) {
            $vars = $this->prepareRow($vars, $records, null, $vars);
        }
        if ($message = ake($info, 'message')) {
            $this->log($message);
        }
        if ($exec = ake($info, 'exec')) {
            if (!is_array($exec)) {
                $exec = [$exec];
            }
            foreach ($exec as $execItem) {
                if (count($vars) > 0) {
                    $execItem = match_replace($execItem, $vars);
                }
                $this->log('EXEC_SQL: '.$execItem);
                if (false === $this->dbi->exec($execItem)) {
                    $this->log(ake($this->dbi->errorInfo(), 2));

                    return false;
                }
            }
        }
        if ($table = ake($info, 'table')) {
            if (true === $this->dbi->table($table)->exists()) {
                $pkey = null;
                if ($constraints = $this->dbi->listConstraints($table, 'PRIMARY KEY')) {
                    $pkey = ake(reset($constraints), 'column');
                } else {
                    throw new Datasync("No primary key found for table '{$table}'.  Does the table exist?");
                }
                if (true === ake($info, 'truncate')) {
                    $this->log('Truncating table: '.$table);
                    if (!$this->dbi->truncate($table, true, true)) {
                        throw new Datasync("Truncating table {$table} failed: ".ake($this->dbi->errorInfo(), 2));
                    }
                    // Reset any sequences in this table that we have just truncated
                    if ($cols = $this->dbi->describeTable($table)) {
                        foreach ($cols as $col) {
                            if (array_key_exists('sequence', $col)) {
                                if (false === $this->dbi->query("ALTER SEQUENCE {$col['sequence']} RESTART;")) {
                                    throw new Datasync(ake($this->dbi->errorInfo(), 2));
                                }
                            }
                        }
                    }
                } elseif (($purge = ake($info, 'purge')) !== null && is_string($purge)) {
                    $this->log('Purging rows from table: '.$table.' WHERE '.$purge);
                    if (!$this->dbi->table($table)->delete($purge)) {
                        throw new Datasync("Purging rows from {$table} failed: ".ake($this->dbi->errorInfo(), 2));
                    }
                }
                if (($source = ake($info, 'source')) && ($remoteTable = ake($info, 'table'))) {
                    $this->log('Syncing rows from remote source');
                    $rows = [];
                    $rowdata = '[]';
                    if ($hostURL = ake($source, 'hostURL')) {
                        if (!isset($source->syncKey)
                            && ($config = ake($source, 'config'))
                            && is_string($config)) {
                            $config = Adapter::loadConfig($config);
                            $source->syncKey = $config['syncKey'];
                        }
                        $context = stream_context_create([
                            'http' => [
                                'method' => 'POST',
                                'header' => 'Content-type: application/javascript',
                                'content' => json_encode($source),
                                'ignore_errors' => true,
                            ],
                        ]);
                        $rowdata = file_get_contents(rtrim($hostURL, ' /').'/hazaar/dbi/sync', false, $context);
                    } elseif ($config = ake($source, 'config')) {
                        $remoteDBI = new Adapter($config);
                        $remoteStatement = $remoteDBI->table($remoteTable)->order($pkey);
                        if ($select = ake($source, 'select')) {
                            $remoteStatement->select((array) $select);
                        }
                        if ($criteria = ake($source, 'criteria')) {
                            $remoteStatement->find((array) $criteria);
                        }
                        $rowdata = json_encode(DataMapper::map($remoteStatement->fetchAll(), ake($source, 'map')));
                    }
                    if ($result = \json_decode($rowdata)) {
                        if ($result instanceof \stdClass && false === $result->ok && isset($result->error)) {
                            if (true !== ake($source, 'ignoreErrors')) {
                                throw new \Exception('REMOTE: '.$result->error->str, $result->error->type);
                            }
                            $this->log('Remote data error: #'.$result->error->type.' - '.$result->error->str);
                        } elseif (is_array($result)) {
                            if (!isset($info->rows)) {
                                $info->rows = [];
                            }
                            // Prepend the new rows to any existing rows as defined rows take priority
                            $info->rows = $result + $info->rows;
                        }
                    }
                }
                // The 'rows' element is used to synchronise table rows in the database.
                if ($rows = ake($info, 'rows')) {
                    if (($def = $this->dbi->describeTable($table)) === false) {
                        throw new Datasync("Can not insert rows into non-existant table '{$table}'!");
                    }
                    $tableDef = array_combine(array_column($def, 'name'), $def);
                    // Set up any data object references
                    if ($refs = ake($info, 'refs')) {
                        $refs = $this->prepareRow($refs, $records, null, $vars);
                    }
                    $this->log('Processing '.count($rows)." records in table '{$table}'");
                    if (!\array_key_exists($table, $records)) {
                        $records[$table] = [];
                    }
                    $sequences = [];
                    foreach ($rows as $rowNum => $row) {
                        if (count($colDiff = array_keys(array_diff_key((array) $row, $tableDef))) > 0) {
                            $this->log("Skipping missing columns in table '{$table}': ".implode(', ', $colDiff));
                            foreach ($colDiff as $key) {
                                unset($row->{$key});
                            }
                        }
                        // Prepare the row by resolving any macros
                        $row = $this->prepareRow($row, $records, $refs, $vars);
                        $doDiff = false;
                        /*
                         * If the primary key is in the record, find the record using only that field, then
                         * we will check for differences between the records
                         */
                        if (isset($row[$pkey])) {
                            $criteria = [$pkey => ake($row, $pkey)];
                            $doDiff = true;
                        // Otherwise, if there are search keys specified, base the search criteria on that
                        } elseif (property_exists($info, 'keys')) {
                            $criteria = [];
                            foreach ($info->keys as $key) {
                                $criteria[trim($key)] = ake($row, $key);
                            }
                            $doDiff = true;
                        // Otherwise, look for the record in it's entirity and only insert if it doesn't exist.
                        } else {
                            $criteria = (array) $row;
                            foreach ($criteria as &$criteriaItem) {
                                if (is_array($criteriaItem) || $criteriaItem instanceof \stdClass) {
                                    $criteriaItem = json_encode($criteriaItem);
                                }
                            }
                        }

                        try {
                            // If this is an insert only row then move on because this row exists
                            if ($current = $this->dbi->table($table)->findOneRow($criteria)) {
                                if (!(ake($info, 'insertonly') || true !== $doDiff)) {
                                    $diff = array_diff_assoc_recursive($row, $current->toArray());
                                    // If nothing has been added to the row, look for child arrays/objects to backwards analyse
                                    if (($changes = count($diff)) === 0) {
                                        foreach ($row as $name => &$col) {
                                            if (!(is_array($col) || $col instanceof \stdClass)) {
                                                continue;
                                            }
                                            $changes += count(array_diff_assoc_recursive(ake($current, $name), $col));
                                        }
                                    }
                                    if ($changes > 0) {
                                        $pkeyValue = ake($current, $pkey);
                                        $this->log("Updating record in table '{$table}' with {$pkey}={$pkeyValue}");
                                        if (!$this->dbi->update($table, $this->fixRow($row, $tableDef), [$pkey => $pkeyValue])) {
                                            throw new Datasync('Update failed: '.$this->dbi->errorInfo()[2]);
                                        }
                                        $current->extend($row);
                                    }
                                }
                                $current = $current->toArray();
                            } elseif (true !== ake($info, 'updateonly')) { // If this is an update only row then move on because this row does not exist
                                if (($pkeyValue = $this->dbi->insert($table, $this->fixRow($row, $tableDef), $pkey)) == false) {
                                    throw new Datasync('Insert failed: '.$this->dbi->errorInfo()[2]);
                                }
                                $row->{$pkey} = $pkeyValue;
                                $this->log("Inserted record into table '{$table}' with {$pkey}={$pkeyValue}");
                                $current = (array) $row;
                            }
                        } catch (\Throwable $e) {
                            throw new Datasync('Row #'.$rowNum.': '.$e->getMessage());
                        }
                        $records[$table][] = $current;
                        // Look for any columns with sequences and increment the sequence if required
                        foreach ($current as $name => $value) {
                            /*Increment the sequence to the current value if:
                             * - There IS a sequence
                             * - The sequence has not been incremented yet
                             * - The queued update is less than the new value
                             */
                            if (array_key_exists($name, $tableDef)
                                && array_key_exists('sequence', $tableDef[$name])
                                && is_int($value)
                                && $value > 0
                                && ($seq = $tableDef[$name]['sequence'])
                                && ((array_key_exists($seq, $sequences) && $sequences[$seq] < $value)
                                        || !\array_key_exists($seq, $sequences))) {
                                $sequences[$seq] = $value;
                            }
                        }
                    }
                    foreach ($sequences as $seqName => $seqValue) {
                        $lastValue = $this->dbi->query("SELECT last_value FROM {$seqName};")->fetchColumn(0);
                        if ($lastValue < $seqValue) {
                            if (!$this->dbi->query("SELECT setval('{$seqName}', {$seqValue})")) {
                                throw new \Exception('Unable to update sequence: '.ake($this->dbi->errorInfo(), 2));
                            }
                        }
                    }
                }
                // The 'update' element is used to trigger updates on existing rows in a database
                if ($updates = ake($info, 'update')) {
                    foreach ($updates as $update) {
                        if (!($where = ake($update, 'where')) && true !== ake($update, 'all', false)) {
                            throw new Datasync("Can not update rows in a table without a 'where' element or setting 'all=true'.");
                        }
                        $affected = $this->dbi->table($table)->update($where, ake($update, 'set'));
                        if (false === $affected) {
                            throw new Datasync('Update failed: '.$this->dbi->errorInfo()[2]);
                        }
                        $this->log("Updated {$affected} rows");
                    }
                }
                // The 'delete' element is used to remove existing rows in a database table
                if ($deletes = ake($info, 'delete')) {
                    foreach ($deletes as $delete) {
                        if (true === ake($delete, 'all', false)) {
                            $affected = $this->dbi->table($table)->deleteAll();
                        } else {
                            if (!($where = ake($delete, 'where'))) {
                                throw new Datasync("Can not delete rows from a table without a 'where' element or setting 'all=true'.");
                            }
                            $affected = $this->dbi->table($table)->delete($where);
                        }
                        if (false === $affected) {
                            throw new Datasync('Delete failed: '.$this->dbi->errorInfo()[2]);
                        }
                        $this->log("Deleted {$affected} rows");
                    }
                }
                if ($copy = ake($info, 'copy')) {
                    if (!($sourceTable = ake($copy, 'sourceTable'))) {
                        throw new Datasync('Data sync copy command requires a source table.');
                    }
                    if (!($targetColumn = ake($copy, 'targetColumn'))) {
                        throw new Datasync('Data sync copy command requires a target index column.');
                    }
                    $map = (array) ake($copy, 'map', []);
                    if (0 === count($map)) {
                        throw new Datasync('Data sync copy command requires a valid column map.');
                    }
                    $set = (array) ake($copy, 'set', []);
                    $sourceSQL = "SELECT * FROM \"{$sourceTable}\" AS \"source\"";
                    if ($where = ake($copy, 'where')) {
                        $sourceSQL .= ' WHERE '.trim($where);
                    }
                    $sourceQuery = $this->dbi->query($sourceSQL);
                    while ($row = $sourceQuery->fetch()) {
                        $updates = [];
                        foreach ($map as $targetCol => $sourceCol) {
                            $updates[$targetCol] = ake($row, $sourceCol);
                        }
                        foreach ($set as $setKey => $setData) {
                            $updates[$setKey] = $setData;
                        }
                        if (($vars = ake($copy, 'vars')) instanceof \stdClass) {
                            $vars = (array) $vars;
                            $this->prepareRow($vars, $records, null, $row);
                        }
                        $this->prepareRow($updates, $records, null, array_merge($vars, $row));
                        if (!array_key_exists($targetColumn, $updates)) {
                            throw new Datasync("Target column '{$targetColumn}' is not being updated in table '{$table}'!");
                        }
                        $existCriteria = [$targetColumn => $updates[$targetColumn]];
                        if ($this->dbi->table($table)->exists($existCriteria)) {
                            $result = $this->dbi->table($table, 'target')->update($existCriteria, array_diff_assoc($updates, $existCriteria));
                        } else {
                            $result = $this->dbi->table($table, 'target')->insert($updates);
                        }
                        if (!$result) {
                            throw new Datasync('Data sync copy error: '.ake($this->dbi->errorInfo(), 2));
                        }
                    }
                }
            } else {
                $this->log("Skipping records in missing table '{$table}'.");
            }
        }

        return true;
    }

    /**
     * Quick closure function to fix up the row ready for insert/update.
     *
     * @param array<mixed>|\stdClass $row
     *
     * @return array<mixed>
     */
    private function fixRow(array|\stdClass &$row, mixed $tableDef): array
    {
        $fixedRow = [];
        foreach ($row as $name => &$col) {
            if (!array_key_exists($name, $tableDef)) {
                throw new Datasync("Attempting to modify data for non-existent row '{$name}'!");
            }
            if (null === $col) {
                continue;
            }
            if ('json' === substr($tableDef[$name]['data_type'], 0, 4)) {
                $col = ['$json' => $col];
            } elseif (is_array($col)) {
                $col = ['$array' => $col];
            }
            $fixedRow[$name] = $col;
        }

        return $fixedRow;
    }

    /**
     * Logs a message to the migration log.
     *
     * @param string $msg The message to log
     */
    private function log(string $msg): void
    {
        $this->migrationLog[] = $line = [
            'time' => microtime(true),
            'msg' => $msg,
        ];
        if ($this->__callback) {
            call_user_func_array($this->__callback, [$line['time'], $line['msg']]);
        }
    }

    private function initDBIFilesystem(): bool
    {
        if (!defined('HAZAAR_VERSION')) {
            return false;
        }
        $config = Config::getInstance('media');
        foreach ($config as $name => $settings) {
            if ('DBI' !== $settings->get('type')) {
                continue;
            }
            $fsDb = null;
            $this->log('Found DBI filesystem: '.$name);

            try {
                $settings->enhance(['dbi' => Adapter::loadConfig(), 'initialise' => true]);
                $fsDb = new Adapter($settings['dbi']);
                if ($fsDb->table('hz_file')->exists() && $fsDb->table('hz_file_chunk')->exists()) {
                    continue;
                }
                if (true !== $settings['initialise']) {
                    throw new Exception\FileSystem($name.' requires initialisation but initialise is disabled!');
                }
                $schema = realpath(__DIR__.str_repeat(DIRECTORY_SEPARATOR.'..', 2)
                    .DIRECTORY_SEPARATOR.'libs'
                    .DIRECTORY_SEPARATOR.'dbi'
                    .DIRECTORY_SEPARATOR.'schema.json');
                $manager = $fsDb->getSchemaManager();
                $this->log('Initialising DBI filesystem: '.$name);
                if (!$manager->applySchemaFromFile($schema)) {
                    throw new Exception\FileSystem('Unable to configure DBI filesystem schema!');
                }
                // Look for the old tables and if they exists, do an upgrade!
                if ($fsDb->table('file')->exists() && $fsDb->table('file_chunk')->exists()) {
                    if (!$fsDb->table('hz_file_chunk')->insert($fsDb->table('file_chunk')->select(['id', null, 'n', 'data']))) {
                        throw $fsDb->errorException();
                    }
                    if (!$fsDb->table('hz_file')->insert($fsDb->table('file')->find(['kind' => 'dir'], ['id', 'kind', ['parent' => 'unnest(parents)'], null, 'filename', 'created_on', 'modified_on', 'length', 'mime_type', 'md5', 'owner', 'group', 'mode', 'metadata']))) {
                        throw $fsDb->errorException();
                    }
                    $fsDb->repair();
                    if (!$fsDb->query("INSERT INTO hz_file (kind, parent, start_chunk, filename, created_on, modified_on, length, mime_type, md5, owner, \"group\", mode, metadata) SELECT kind, unnest(parents) as parent, (SELECT fc.id FROM file_chunk fc WHERE fc.file_id=f.id), filename, created_on, modified_on, length, mime_type, md5, owner, \"group\", mode, metadata FROM file f WHERE kind = 'file'")) {
                        throw $fsDb->errorException();
                    }
                }
            } catch (\Throwable $e) {
                $this->log($e->getMessage());

                continue;
            }
        }

        return true;
    }

    private function getSchemaManagerDirectory(): string
    {
        $dbDir = Loader::getFilePath(FilePath::DB);
        if (file_exists($dbDir)) {
            if (!is_dir($dbDir)) {
                throw new Schema("The database directory '{$dbDir}' exists but is not a directory!");
            }

            return $dbDir;
        }
        if (!is_writable(dirname($dbDir))) {
            throw new Schema('The directory that contains the database directory is not writable!');
        }
        mkdir($dbDir);

        return $dbDir;
    }
}
