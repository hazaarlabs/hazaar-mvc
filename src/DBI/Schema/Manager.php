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
use Hazaar\DBI\Adapter;
use Hazaar\DBI\DataMapper;
use Hazaar\DBI\Exception\ConnectionFailed;
use Hazaar\DBI\Result;
use Hazaar\DBI\Schema\Exception\Datasync;
use Hazaar\DBI\Schema\Exception\Schema;
use Hazaar\File;

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
    private array $versions = [];
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
        $managerConfig = array_merge($this->dbiConfig, $this->dbiConfig['manager'] ?? []);
        $this->ignoreTables[] = self::$schemaInfoTable;

        try {
            $this->log("Connecting to database '{$managerConfig['dbname']}' on host '{$managerConfig['host']}'");
            $this->dbi = new Adapter($managerConfig);
        } catch (ConnectionFailed $e) {
            if (7 !== $e->getCode() || true !== ake($managerConfig, 'createDatabase')) {
                throw $e;
            }
            $this->log('Database does not exist.  Attempting to create it as requested.');
            $managerConfig['dbname'] = isset($managerConfig['maintenanceDatabase'])
                ? $managerConfig['maintenanceDatabase']
                : $managerConfig['user'];
            $this->log("Connecting to database '{$managerConfig['dbname']}' on host '{$managerConfig['host']}'");
            $maintDB = new Adapter($managerConfig);
            $this->log("Creating database '{$managerConfig['dbname']}'");
            $maintDB->createDatabase($managerConfig['dbname']);
            $this->log("Retrying connection to database '{$managerConfig['dbname']}' on host '{$managerConfig['host']}'");
            $this->dbi = new Adapter($managerConfig);
        }
        $this->dbDir = realpath(defined('HAZAAR_VERSION') ? APPLICATION_PATH.DIRECTORY_SEPARATOR.'..' : getcwd()).DIRECTORY_SEPARATOR.'db';
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
        if (!count($this->versions) > 0) {
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
        $versions = $returnFullPath ? $this->versions[0] : $this->versions[1];
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
                    list($elem, $source, $content_type) = $map;
                    if (!array_key_exists($elem, $schema)) {
                        $schema[$elem] = [];
                    }
                    if (false !== $source) {
                        if ('alter' === $action) {
                            foreach ($items as $table => $alterations) {
                                if (true === $source) {
                                    $schema[$elem][$alterations['name']] = $alterations;
                                } else {
                                    foreach ($alterations as $alt_action => $alt_columns) {
                                        if ('drop' === $alt_action) {
                                            if (!isset($schema['tables'][$table])) {
                                                throw new \Exception("Drop action on table '{$table}' which does not exist!");
                                            }
                                            // Remove the column from the table schema
                                            $schema['tables'][$table] = array_filter($schema['tables'][$table], function ($item) use ($alt_columns) {
                                                return !in_array($item['name'], $alt_columns);
                                            });
                                            // Update any constraints/indexes that reference this column
                                            if (isset($schema['constraints'])) {
                                                $schema['constraints'] = array_filter($schema['constraints'], function ($item) use ($alt_columns) {
                                                    return !in_array($item['column'], $alt_columns);
                                                });
                                            }
                                            if (isset($schema['indexes'])) {
                                                $schema['indexes'] = array_filter($schema['indexes'], function ($item) use ($table, $alt_columns) {
                                                    return $item['table'] !== $table || 0 === count(array_intersect($item['columns'], $alt_columns));
                                                });
                                            }
                                        } else {
                                            foreach ($alt_columns as $col_name => $col_data) {
                                                if ('add' === $alt_action) {
                                                    $schema['tables'][$table][] = $col_data;
                                                } elseif ('alter' === $alt_action && array_key_exists($table, $schema['tables'])) {
                                                    foreach ($schema['tables'][$table] as &$col) {
                                                        if ($col['name'] !== $col_name) {
                                                            continue;
                                                        }
                                                        // If we are renaming the column, we need to update index and constraints
                                                        if (array_key_exists('name', $col_data) && $col['name'] !== $col_data['name']) {
                                                            if (isset($schema['constraints'])) {
                                                                array_walk($schema['constraints'], function (&$item) use ($col_name, $col_data) {
                                                                    if ($item['column'] === $col_name) {
                                                                        $item['column'] = $col_data['name'];
                                                                    }
                                                                });
                                                            }
                                                            if (isset($schema['indexes'])) {
                                                                array_walk($schema['indexes'], function (&$item) use ($col_name, $col_data) {
                                                                    if (in_array($col_name, $item['columns'])) {
                                                                        $item['columns'][array_search($col_name, $item['columns'])] = $col_data['name'];
                                                                    }
                                                                });
                                                            }
                                                        }
                                                        // If the column data type is changing and there is no 'length' property, set the length to null.
                                                        if (array_key_exists('data_type', $col_data)
                                                            && !array_key_exists('length', $col_data)
                                                            && $col['data_type'] !== $col_data['data_type']) {
                                                            $col_data['length'] = null;
                                                        }
                                                        $col = array_merge($col, $col_data);

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
                                        foreach ($schema[$elem] as $index => $child_item) {
                                            if (ake($child_item, 'name') === $item['name']
                                                && ake($child_item, 'table') === ake($item, 'table')) {
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
                        foreach ($items as $item_name => $item) {
                            if (is_string($item)) {
                                if ('create' === $action) {
                                    $schema[$elem][] = $item;
                                } elseif ('remove' === $action) {
                                    foreach ($schema[$elem] as $schema_item_name => &$schema_item) {
                                        if (is_array($schema_item)) {
                                            if (!array_key_exists($item, $schema_item)) {
                                                continue;
                                            }
                                            unset($schema_item[$item]);
                                        } elseif ($schema_item !== $item) {
                                            continue;
                                        } else {
                                            unset($schema[$elem][$schema_item_name]);
                                        }

                                        break;
                                    }
                                }
                            // Functions removed are a bit different as we have to look at parameters.
                            } elseif ('function' === $type && 'remove' === $action) {
                                if (array_key_exists($item_name, $schema[$elem])) {
                                    foreach ($item as $params) {
                                        // Find the existing function and remove it
                                        foreach ($schema[$elem][$item_name] as $index => $func) {
                                            $c_params = array_map(function ($item) {
                                                return ake($item, 'type');
                                            }, ake($func, 'parameters'));
                                            // We do an array_diff_assoc so that parameter position is taken into account
                                            if (0 === count(array_diff_assoc($params, $c_params)) && 0 === count(array_diff_assoc($c_params, $params))) {
                                                unset($schema[$elem][$item_name][$index]);
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
                                    $schema[$elem][$item['name']][] = $item;
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
                    if ($content_type) {
                        foreach ($schema[$elem] as &$content_item) {
                            if (true === $source) {
                                $this->processContent($version, $content_type, $content_item);
                            } else {
                                foreach ($content_item as &$content_group) {
                                    $this->processContent($version, $content_type, $content_group);
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
     * @param string $comment          a comment to add to the migration file
     * @param bool   $test             Test only.  Does not make any changes.
     * @param int    $override_version Manually specify the version number.  Default is to use current timestamp.
     *
     * @return bool True if the snapshot was successful. False if no changes were detected and nothing needed to be done.
     *
     * @throws Exception\Snapshot
     */
    public function snapshot(?string $comment = null, bool $test = false, ?int $override_version = null)
    {
        $this->log('Snapshot process starting');
        if ($test) {
            $this->log('Test mode ENABLED');
        }
        $this->log('APPLICATION_ENV: '.APPLICATION_ENV);
        if ($versions = $this->getVersions()) {
            end($versions);
            $latest_version = key($versions);
        } else {
            $latest_version = 0;
        }
        $version = $this->getVersion();
        if ($latest_version > $version) {
            throw new Exception\Snapshot('Snapshoting a database that is not at the latest schema version is not supported.');
        }
        $this->dbi->beginTransaction();
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
        $version = $override_version ?? date('YmdHis');

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
                    foreach ($diff as $diff_mode => $col_diff) {
                        foreach ($col_diff as $col_name => $col_info) {
                            if ('drop' === $diff_mode) {
                                $info = $this->getColumn($col_info, $schema['tables'][$name]);
                                $changes['down']['table']['alter'][$name]['add'][$col_name] = $info;
                            } elseif ('alter' == $diff_mode) {
                                $info = $this->getColumn($col_name, $schema['tables'][$name]);
                                $inverse_diff = array_intersect_key($info, $col_info);
                                $changes['down']['table']['alter'][$name]['alter'][$col_name] = $inverse_diff;
                            } elseif ('add' === $diff_mode) {
                                $changes['down']['table']['alter'][$name]['drop'][] = $col_name;
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
                        foreach ($schema['constraints'][$table] as $constraint_name => $constraint) {
                            $changes['down']['constraint']['create'][] = array_merge($constraint, [
                                'name' => $constraint_name,
                                'table' => $table,
                            ]);
                        }
                    }
                    // Add any indexes that were on this table to the down script so they get re-created
                    if (array_key_exists('indexes', $schema)
                        && array_key_exists($table, $schema['indexes'])) {
                        $changes['down']['index']['create'] = [];
                        foreach ($schema['indexes'][$table] as $index_name => $index) {
                            $changes['down']['index']['create'][] = array_merge($index, [
                                'name' => $index_name,
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
            foreach ($changes['up']['table']['create'] as $create_key => $create) {
                foreach ($changes['up']['table']['remove'] as $remove_key => $remove) {
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
                        $changes['up']['table']['create'][$create_key] = null;
                        $changes['up']['table']['remove'][$remove_key] = null;
                        foreach ($changes['down']['table']['remove'] as $down_remove_key => $down_remove) {
                            if ($down_remove === $create['name']) {
                                $changes['down']['table']['remove'][$down_remove_key] = null;
                            }
                        }
                        foreach ($changes['down']['table']['create'] as $down_create_key => $down_create) {
                            if ($down_create['name'] == $remove) {
                                $changes['down']['table']['create'][$down_create_key] = null;
                            }
                        }
                    }
                }
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
            foreach ($constraints as $constraint_name => $constraint) {
                if (!array_key_exists($constraint_name, $schema['constraints'])) {
                    $this->log("+ Added new constraint '{$constraint_name}'.");
                    $changes['up']['constraint']['create'][] = array_merge([
                        'name' => $constraint_name,
                    ], $constraint);
                    // If the constraint was added at the same time as the table, we don't need to add the removes
                    if (!$init && !in_array($constraint['table'], $changes['down']['table']['remove'])) {
                        $changes['down']['constraint']['remove'][] = ['name' => $constraint_name, 'table' => $constraint['table']];
                    }
                }
            }
            $this->log('Looking for removed constraints');
            // Look for any removed constraints.  If there are no constraints in the current schema, then all have been removed.
            $missing = array_diff(array_keys($schema['constraints']), array_keys($currentSchema['constraints']));
            if (count($missing) > 0) {
                foreach ($missing as $constraint_name) {
                    $this->log("- Constraint '{$constraint_name}' has been removed.");
                    $idef = $schema['constraints'][$constraint_name];
                    $changes['up']['constraint']['remove'][] = $constraint_name;
                    $changes['down']['constraint']['create'][] = array_merge([
                        'name' => $constraint_name,
                    ], $idef);
                }
            }
        } elseif (count($constraints) > 0) {
            foreach ($constraints as $constraint_name => $constraint) {
                $this->log("+ Added new constraint '{$constraint_name}'.");
                $changes['up']['constraint']['create'][] = array_merge([
                    'name' => $constraint_name,
                ], $constraint);
                if (!$init) {
                    $changes['down']['constraint']['remove'][] = ['name' => $constraint_name, 'table' => $constraint['table']];
                }
            }
        } // END PROCESSING CONSTRAINTS
        $this->log('*** SNAPSHOTTING INDEXES ***');
        // BEGIN PROCESSING INDEXES
        $indexes = array_filter($this->dbi->listIndexes(), function ($item) {
            return !in_array($item['table'], $this->ignoreTables);
        });
        if (count($indexes) > 0) {
            foreach ($indexes as $index_name => $index) {
                // Check if the index is actually a constraint
                if (array_key_exists($index_name, $currentSchema['constraints'])) {
                    continue;
                }
                $currentSchema['indexes'][$index_name] = $index;
            }
        }
        if (array_key_exists('indexes', $schema)) {
            $this->log('Looking for new indexes.');
            // Look for new indexes
            foreach ($indexes as $index_name => $index) {
                // Check if the index is actually a constraint
                if (array_key_exists($index_name, $currentSchema['constraints'])) {
                    continue;
                }
                if (array_key_exists($index_name, $schema['indexes'])) {
                    continue;
                }
                $this->log("+ Added new index '{$index_name}'.");
                $changes['up']['index']['create'][] = array_merge([
                    'name' => $index_name,
                ], $index);
                if (!$init) {
                    $changes['down']['index']['remove'][] = $index_name;
                }
            }
            $this->log('Looking for removed indexes');
            // Look for any removed indexes.  If there are no indexes in the current schema, then all have been removed.
            $missing = array_diff(array_keys($schema['indexes']), array_keys($currentSchema['indexes']));
            if (count($missing) > 0) {
                foreach ($missing as $index_name) {
                    $this->log("- Index '{$index_name}' has been removed.");
                    $idef = $schema['indexes'][$index_name];
                    $changes['up']['index']['remove'][] = $index_name;
                    $changes['down']['index']['create'][] = array_merge([
                        'name' => $index_name,
                    ], $idef);
                }
            }
        } elseif (count($indexes) > 0) {
            foreach ($indexes as $index_name => $index) {
                // Check if the index is actually a constraint
                if (array_key_exists($index_name, $currentSchema['constraints'])) {
                    continue;
                }
                $this->log("+ Added new index '{$index_name}'.");
                $changes['up']['index']['create'][] = array_merge([
                    'name' => $index_name,
                ], $index);
                if (!$init) {
                    $changes['down']['index']['remove'][] = $index_name;
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
                if (true === $this->dbiConfig['manager']['functionsInFiles']) {
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
                    && count($ex_info = array_filter($schema['functions'][$name], function ($item) use ($info) {
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
                    foreach ($ex_info as $e) {
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
            foreach ($schema['functions'] as $func_name => $func_instances) {
                $missing_func = null;
                foreach ($func_instances as $func) {
                    if (array_key_exists($func_name, $currentSchema['functions'])
                        && count($currentSchema['functions']) > 0) {
                        $p1 = ake($func, 'parameters', []);
                        foreach ($currentSchema['functions'][$func_name] as $c_func) {
                            $p2 = ake($c_func, 'parameters', []);
                            if (0 === count(array_diff_assoc_recursive($p1, $p2))
                                && 0 === count(array_diff_assoc_recursive($p2, $p1))) {
                                continue 2;
                            }
                        }
                    }
                    if (!array_key_exists($func_name, $missing)) {
                        $missing[$func_name] = [];
                    }
                    $missing[$func_name][] = $func;
                }
            }
            if (count($missing) > 0) {
                foreach ($missing as $func_name => $func_instances) {
                    foreach ($func_instances as $func) {
                        $params = [];
                        foreach (ake($func, 'parameters', []) as $param) {
                            $params[] = $param['type'];
                        }
                        $func_full_name = $func_name.'('.implode(', ', $params).')';
                        $this->log("- Function '{$func_full_name}' has been removed.");
                        $changes['up']['function']['remove'][$func_name][] = $params;
                        if (!array_key_exists($func_name, $changes['down']['function']['create'])) {
                            $changes['down']['function']['create'][$func_name] = [];
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
            if (!($info = $this->dbi->describeTrigger($trigger['name'], $trigger['schema']))) {
                throw new Exception\Snapshot("Error getting trigger definition for '{$name}'.  Does the connected user have the correct permissions?");
            }
            if (true === $this->dbiConfig['manager']['functionsInFiles']) {
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
                $this->dbi->rollback();

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
                $this->dbi->rollback();

                return ake($changes, 'up');
            }
            // Save the migrate diff file
            if (!file_exists($this->migrateDir)) {
                $this->log('Migration directory does not exist.  Creating.');
                mkdir($this->migrateDir);
            }
            $migrate_file = $this->migrateDir.'/'.$version.'_'.preg_replace('/[^A-Za-z0-9]/', '_', trim($comment)).'.json';
            $this->log("Writing migration file to '{$migrate_file}'");
            file_put_contents($migrate_file, json_encode($changes, JSON_PRETTY_PRINT));
            if (!empty($functions)) {
                if (!file_exists($this->migrateDir.'/functions')) {
                    mkdir($this->migrateDir.'/functions');
                }
                if (!file_exists($this->migrateDir.'/functions/'.$version)) {
                    mkdir($this->migrateDir.'/functions/'.$version);
                }
                foreach ($functions as $name => $content) {
                    $func_file = $this->migrateDir.'/functions/'.$version.'/'.$name.'.sql';
                    $this->log("Writing function file to '{$func_file}'");
                    file_put_contents($func_file, $content);
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
        bool $force_reinitialise = false
    ): bool {
        $this->log('Migration process starting');
        if ($test) {
            $this->log('Test mode ENABLED');
        }
        $this->log('APPLICATION_ENV: '.APPLICATION_ENV);
        $mode = 'up';
        $currentVersion = 0;
        $versions = $this->getVersions(true);
        $latest_version = $this->getLatestVersion();
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
                $version = $latest_version;
                $this->log('Initialising database at version: '.$version);
            }
        }

        // Check that the database exists and can be written to.
        try {
            $schemaName = $this->dbi->getSchemaName();
            if (!$this->dbi->schemaExists($schemaName)) {
                $this->log('Database does not exist.  Creating...');
                $this->dbi->createSchema($schemaName);
                if (($dbiUser = $this->dbiConfig['user']) && $this->dbi->config['user'] !== $dbiUser) {
                    $this->dbi->query("GRANT USAGE ON SCHEMA {$schemaName} TO {$dbiUser};");
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
        if ($this->dbi->tableExists(self::$schemaInfoTable)) {
            $result = $this->dbi->table(self::$schemaInfoTable)->find([], ['version'])->sort('version', SORT_DESC);
            if ($row = $result->fetch()) {
                $currentVersion = $row['version'];
                $this->log('Current database version: '.($currentVersion ? $currentVersion : 'None'));
            }
        }
        $roles = [];
        if (true === $this->dbi->config['createRole'] && isset($this->dbiConfig['user'])) {
            $roles[] = [
                'name' => $this->dbiConfig['user'],
                'password' => $this->dbiConfig['password'] ?? '',
                'privileges' => ['LOGIN'],
            ];
        }
        if (isset($this->dbi->config['roles'])) {
            $roles = array_merge($roles, $this->dbi->config['roles']);
        }
        if (count($roles) > 0) {
            $this->createRoleIfNotExists($roles);
        }
        if (true === $force_reinitialise) {
            $this->log('WARNING: Forcing full database re-initialisation.  THIS WILL DELETE ALL DATA!!!');
            $this->log('IF YOU DO NOT WANT TO DO THIS, YOU HAVE 10 SECONDS TO CANCEL');
            sleep(10);
            $this->log('DELETING YOUR DATA!!!  YOU WERE WARNED!!!');
            $views = $this->dbi->listViews();
            foreach ($views as $view) {
                $this->dbi->dropView($view['name']);
            }
            $last_tables = [];
            for ($i = 0; $i < 255; ++$i) {
                $tables = $this->dbi->listTables();
                if (0 === count($tables)) {
                    break;
                }
                if (count($tables) === count($last_tables) && $i > count($tables)) {
                    $this->log('Got stuck trying to resolve drop dependencies. Aborting!');

                    return false;
                }
                foreach ($tables as $table) {
                    try {
                        $this->dbi->dropTable($table['name']);
                    } catch (\Throwable $e) {
                    }
                }
                $last_tables = $tables;
            }
            if (254 === $i) {
                exit('Something really BAD happened!');
            }
            $functions = $this->dbi->listFunctions(null, true);
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
        if (0 === $currentVersion && $version === $latest_version) {
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
                    $missing_versions = $this->getMissingVersions($version);
                    foreach ($missing_versions as $ver) {
                        $this->dbi->insert(self::$schemaInfoTable, ['version' => $ver]);
                    }
                }
            }
            $forceDataSync = true;
        } else {
            $this->log("Migrating to version '{$version}'.");
            // Compare known versions with the versions applied to the database and get a list of missing versions less than the requested version
            $missing_versions = $this->getMissingVersions($version, $appliedVersions);
            if (($count = count($missing_versions)) > 0) {
                $this->log("Found {$count} missing versions that will get replayed.");
            }
            $migrations = array_combine($missing_versions, array_fill(0, count($missing_versions), 'up'));
            ksort($migrations);
            if ($version < $currentVersion) {
                $down_migrations = [];
                foreach ($versions as $ver => $info) {
                    if ($ver > $version && $ver <= $currentVersion && in_array($ver, $appliedVersions)) {
                        $down_migrations[$ver] = 'down';
                    }
                }
                krsort($down_migrations);
                $migrations = $migrations + $down_migrations;
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
                        $this->dbi->beginTransaction();
                        if (true !== $this->replay($currentSchema[$mode], $test, $globalVars, $ver)) {
                            $this->dbi->rollback();

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
                        $this->dbi->rollback();

                        throw $e;
                    }
                    $this->log("-- Replay of version '{$ver}' completed.");
                } while ($source = next($versions));
                if ('up' === $mode && $version === $latest_version) {
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
            $this->dbi->beginTransaction();
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
            $this->dbi->rollback();

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
        $this->log('Rollbacking back version '.$version.($test ? ' in TEST MODE' : ''));
        $versions = $this->getVersions(true, true);
        if (!array_key_exists($version, $versions)) {
            $this->log('Version '.$version.' is not currently applied to the schema');

            return false;
        }
        $items = ake(json_decode(file_get_contents($versions[$version]), true), 'down');
        $version_list = array_keys($versions);
        sort($version_list);

        /**
         * LOOK FOR DEPENDENTS SO WE CAN ROLL BACK THOSE VERSIONS AS WELL.
         */
        $modified_tables = array_merge(ake($items, 'table.remove', []), array_keys(ake($items, 'table.alter', [])));
        $start = array_search($version, $version_list);
        $dependents = [];
        for ($i = $start + 1; $i < count($version_list); ++$i) {
            $dependent = false;
            $compare_version = $version_list[$i];
            if (!array_key_exists($compare_version, $versions)) {
                throw new \Exception('Something weird happened:  List had version number that is not in version array!');
            }
            $version_item = json_decode(file_get_contents($versions[$compare_version]), true);
            if (!($compare_item = ake($version_item, 'up'))) {
                continue;
            }
            // Migrations that alter a modified table
            if (($table_alter = ake($compare_item, 'table.alter'))
                && count(array_intersect(array_keys($table_alter), $modified_tables)) > 0) {
                $dependent = true;
            }
            // Migrations that remove a modified table
            if (($table_remove = ake($compare_item, 'table.remove'))
                && count(array_intersect($table_remove, $modified_tables)) > 0) {
                $dependent = true;
            }
            // Migrations that create constraints on or referencing a modified table
            if ($constraint_create = ake($compare_item, 'constraint.create')) {
                foreach ($constraint_create as $constraint) {
                    if ((($table = ake($constraint, 'table')) && false !== array_search($table, $modified_tables))
                        || ($references_table = ake($constraint, 'references.table')) && false !== array_search($references_table, $modified_tables)) {
                        $dependent = true;
                    }
                }
            }
            // Migrations that create constraints on or referencing a modified table
            if (ake($compare_item, 'constraint.remove')) {
                if (!is_array($constraint_items = ake($version_item, 'down.constraint.create'))) {
                    continue;
                }
                foreach ($constraint_items as $constraint) {
                    if ((($table = ake($constraint, 'table')) && false !== array_search($table, $modified_tables))
                        || ($references_table = ake($constraint, 'references.table')) && false !== array_search($references_table, $modified_tables)) {
                        $dependent = true;
                    }
                }
            }
            // Migrations that create indexes on a modified table
            if ($index_create = ake($compare_item, 'index.create')) {
                foreach ($index_create as $index) {
                    if (false !== array_search(ake($index, 'table'), $modified_tables)) {
                        $dependent = true;
                    }
                }
            }
            // Migrations that remove indexes on a modified table
            if (ake($compare_item, 'index.remove')) {
                if (!is_array($index_items = ake($version_item, 'down.index.create'))) {
                    continue;
                }
                foreach ($index_items as $index_item) {
                    if (false !== array_search(ake($index_item, 'table'), $modified_tables)) {
                        $dependent = true;
                    }
                }
            }
            if (true === $dependent) {
                $this->log('- '.$version.' depends on '.$compare_version);
                $dependents[] = $compare_version;
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
            $this->dbi->beginTransaction();
            if ($this->replay($items, $test) === $test) {
                $this->dbi->rollback();

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
            $this->dbi->rollback();

            throw $e;
        }
        $rollbacks[] = $version;
        $this->log("rollback of version '{$version}' completed.");

        return true;
    }

    /**
     * Takes a schema definition and creates it in the database.
     *
     * @param array<mixed> $schema
     */
    public function applySchema(array $schema, bool $test = false, bool $keepTables = false): bool
    {
        $this->dbi->beginTransaction();

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
                    if (true === $keepTables && $this->dbi->tableExists($table)) {
                        $cur_columns = $this->dbi->describeTable($table);
                        $diff = array_diff_assoc_recursive($cur_columns, $columns);
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
                    if (($dbi_user = $this->dbiConfig['user']) && $this->dbi->config['user'] !== $dbi_user) {
                        $this->dbi->grant($table, $dbi_user, ['INSERT', 'SELECT', 'UPDATE', 'DELETE']);
                    }
                }
            }
            // Create foreign keys
            if ($constraints = ake($schema, 'constraints')) {
                // Do primary keys first
                $cur_constraints = $this->dbi->listConstraints();
                foreach ($constraints as $constraint_name => $constraint) {
                    if ('PRIMARY KEY' !== $constraint['type']) {
                        continue;
                    }
                    if (true === $keepTables && array_key_exists($constraint_name, $cur_constraints)) {
                        $diff = array_diff_assoc_recursive($cur_constraints[$constraint_name], $constraint);
                        if (count($diff) > 0) {
                            throw new Schema('Constraint "'.$constraint_name.'" already exists but is different.  Bailing out!');
                        }
                        $this->log('Constraint "'.$constraint_name.'" already exists and looks current.  Skipping.');

                        continue;
                    }
                    $ret = $this->dbi->addConstraint($constraint_name, $constraint);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating constraint '.$constraint_name.': '.$this->dbi->errorInfo()[2]);
                    }
                }
                // Now do all other constraints
                foreach ($constraints as $constraint_name => $constraint) {
                    if ('PRIMARY KEY' === $constraint['type']) {
                        continue;
                    }
                    if (true === $keepTables && array_key_exists($constraint_name, $cur_constraints)) {
                        $diff = array_diff_assoc_recursive($cur_constraints[$constraint_name], $constraint);
                        if (count($diff) > 0) {
                            throw new Schema('Constraint "'.$constraint_name.'" already exists but is different.  Bailing out!');
                        }
                        $this->log('Constraint "'.$constraint_name.'" already exists and looks current.  Skipping.');

                        continue;
                    }
                    $ret = $this->dbi->addConstraint($constraint_name, $constraint);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating constraint '.$constraint_name.': '.$this->dbi->errorInfo()[2]);
                    }
                }
            }
            // Create indexes
            if ($indexes = ake($schema, 'indexes')) {
                foreach ($indexes as $index_name => $index_info) {
                    $ret = $this->dbi->createIndex($index_name, $index_info['table'], $index_info);
                    if (!$ret || $this->dbi->errorCode() > 0) {
                        throw new Schema('Error creating index '.$index_name.': '.$this->dbi->errorInfo()[2]);
                    }
                }
            }
            // Create views
            if ($views = ake($schema, 'views')) {
                foreach ($views as $view => $info) {
                    if (true === $keepTables && $this->dbi->viewExists($view)) {
                        $cur_info = $this->dbi->describeView($view);
                        $diff = array_diff_assoc_recursive($cur_info, $info);
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
                    if (($dbi_user = $this->dbiConfig['user']) && $this->dbi->config['user'] !== $dbi_user) {
                        $this->dbi->grant($view, $dbi_user, ['SELECT']);
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
                $cur_triggers = [];
                foreach ($triggers as $name => $info) {
                    if (true === $keepTables) {
                        if (!array_key_exists($info['table'], $cur_triggers)) {
                            $cur_triggers[$info['table']] = array_collate($this->dbi->listTriggers($info['table']), 'name');
                        }
                        if (array_key_exists($info['name'], $cur_triggers[$info['table']])) {
                            $cur_info = $this->dbi->describeTrigger($info['name']);
                            $diff = array_diff_assoc_recursive($cur_info, $info);
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
            $this->dbi->rollback();

            throw $e;
        }
        if (true === $test) {
            $this->dbi->rollback();

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
        $this->log('APPLICATION_ENV: '.APPLICATION_ENV);
        if (null === $dataSchema) {
            $schema = $this->getSchema($this->getVersion());
            $dataSchema = array_key_exists('data', $schema) ? $schema['data'] : [];
            $this->loadDataFromFile($dataSchema, $this->dataFile);
        }
        $sync_hash = md5(json_encode($dataSchema));
        $sync_hash_file = Application::getInstance()->getRuntimePath('.dbi_sync_hash');
        if (true !== $forceDataSync
            && file_exists($sync_hash_file)
            && $sync_hash == trim(file_get_contents($sync_hash_file))) {
            $this->log('Sync hash is unchanged.  Skipping data sync.');

            return true;
        }
        $this->log('Starting DBI data sync on schema version '.$this->getVersion());
        $this->currentVersion = null;  // Removed to force reload
        $this->appliedVersions = null; // Removed to force reload
        $this->dbi->beginTransaction();
        $globalVars = new \stdClass();

        try {
            $records = [];
            foreach ($dataSchema as $info) {
                $this->processDataObject($info, $records, $globalVars);
            }
            if ($test) {
                $this->dbi->rollback();
            } else {
                $this->dbi->commit();
            }
            $this->log('DBI Data sync completed successfully!');
            $this->log('Running '.$this->dbi->driver.' repair process');
            $result = $this->dbi->driver->repair();
            $this->log('Repair '.($result ? 'completed successfully' : 'failed'));
        } catch (\Throwable $e) {
            $this->dbi->rollback();
            $this->log('DBI Data sync error: '.$e->getMessage());
        }
        if (false === file_exists($sync_hash_file) || is_writable($sync_hash_file)) {
            file_put_contents($sync_hash_file, $sync_hash);
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
     * @param array<array<mixed>>|string $roleOrRoles
     */
    public function createRoleIfNotExists(array|string $roleOrRoles): void
    {
        $roles = is_array($roleOrRoles) ? $roleOrRoles : [$roleOrRoles];
        $currentRoles = array_merge($this->dbi->listUsers(), $this->dbi->listGroups());
        foreach ($roles as $role) {
            if (in_array($role['name'], $currentRoles)) {
                continue;
            }
            $this->log('Creating role: '.$role['name']);
            $privileges = ['INHERIT'];
            if (array_key_exists('privileges', $role)) {
                if (is_array($role['privileges'])) {
                    $privileges = array_merge($privileges, $role['privileges']);
                } else {
                    $privileges[] = $role['privileges'];
                }
            }
            if (!$this->dbi->createRole($role['name'], $role['password'], $privileges)) {
                throw new \Exception("Error creating role '{$role['name']}': ".ake($this->dbi->errorInfo(), 2));
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

    /**
     * Creates the info table that stores the version info of the current database.
     */
    private function createInfoTable(): bool
    {
        if (!$this->dbi->tableExists(Manager::$schemaInfoTable)) {
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
            if (($old_column = $this->getColumn($col['name'], $old)) !== null) {
                $column_diff = [];
                foreach ($col as $key => $value) {
                    if ((array_key_exists($key, $old_column) && $value !== $old_column[$key])
                    || (!array_key_exists($key, $old_column) && null !== $value)) {
                        $column_diff[$key] = $value;
                    }
                }
                if (count($column_diff) > 0) {
                    $this->log("> Column '{$col['name']}' has changed");
                    $diff['alter'][$col['name']] = $column_diff;
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
     * @param array<mixed> $schema The JSON decoded schema to replay
     */
    private function replay(
        array $schema,
        bool $test = false,
        ?\stdClass &$globalVars = null,
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
            $pk_items = array_filter($items, function ($i) {
                return 'PRIMARY KEY' === $i['type'];
            });
            $this->replayItems($type, $action, $pk_items, $test, false, $version);
            $items = array_filter($items, function ($i) {
                return 'PRIMARY KEY' !== $i['type'];
            });
        }
        foreach ($items as $item_name => $item) {
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
                        if (($dbi_user = $this->dbiConfig['user']) && $dbi_user != $this->dbi->config['user']) {
                            $this->dbi->grant($item['name'], $dbi_user, ['INSERT', 'SELECT', 'UPDATE', 'DELETE']);
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
                        if (($dbi_user = $this->dbiConfig['user']) && $dbi_user != $this->dbi->config['user']) {
                            $this->dbi->grant($item['name'], $dbi_user, ['SELECT']);
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
                            && ($dbi_user = $this->dbiConfig['user'])
                            && $dbi_user != $this->dbi->config['user']) {
                            $this->dbi->grant('FUNCTION '.$item['name'], $dbi_user, ['EXECUTE']);
                        }
                    } elseif ('trigger' === $type) {
                        $this->log("+ Creating trigger '{$item['name']}' on table '{$item['table']}'.");
                        if ($test) {
                            break;
                        }
                        $this->processContent($version, 'functions', $item);
                        $this->dbi->createTrigger($item['name'], $item['table'], $item);
                        if (true === ake($items, 'grant')
                            && ($dbi_user = $this->dbiConfig['user'])
                            && $dbi_user != $this->dbi->config['user']) {
                            $this->dbi->grant('FUNCTION '.$item['name'], $dbi_user, ['EXECUTE']);
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
                    $this->log("> Altering {$type} {$item_name}");
                    if ('table' === $type) {
                        foreach ($item as $alter_action => $columns) {
                            foreach ($columns as $col_name => $col) {
                                if ('add' == $alter_action) {
                                    $this->log("+ Adding column '{$col['name']}'.");
                                    if ($test) {
                                        break;
                                    }
                                    $this->dbi->addColumn($item_name, $col);
                                } elseif ('alter' == $alter_action) {
                                    $this->log("> Altering column '{$col_name}'.");
                                    if ($test) {
                                        break;
                                    }
                                    $this->dbi->alterColumn($item_name, $col_name, $col);
                                } elseif ('drop' == $alter_action) {
                                    $this->log("- Dropping column '{$col}'.");
                                    if ($test) {
                                        break;
                                    }
                                    $this->dbi->dropColumn($item_name, $col, true);
                                }
                                if ($this->dbi->errorCode() > 0) {
                                    throw new Exception\Migration($this->dbi->errorInfo()[2]);
                                }
                            }
                        }
                        if (($dbi_user = $this->dbiConfig['user']) && $dbi_user != $this->dbi->config['user']) {
                            $this->dbi->grant($item_name, $dbi_user, ['INSERT', 'SELECT', 'UPDATE', 'DELETE']);
                        }
                    } elseif ('view' === $type) {
                        if ($test) {
                            break;
                        }
                        $this->dbi->dropView($item_name, false, true);
                        if ($this->dbi->errorCode() > 0) {
                            throw new Exception\Migration($this->dbi->errorInfo()[2]);
                        }
                        $this->dbi->createView($item_name, $item['content']);
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
     * @param array<\stdClass> $records
     */
    private function prepareRow(
        \stdClass &$row,
        array &$records = [],
        ?\stdClass $refs = null,
        ?\stdClass $vars = null
    ): \stdClass {
        $row_refs = [];
        if ($refs instanceof \stdClass) {
            $row = (object) array_merge((array) $row, (array) $refs);
        }
        if (null === $vars) {
            $vars = new \stdClass();
        }
        // Find any macros that have been defined in record fields
        foreach (get_object_vars($row) as $columnName => &$field) {
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
            $row_refs[$columnName] = $ref;
        }
        // Process any macros that have been defined in record fields
        foreach ($row_refs as $columnName => $ref) {
            $found = false;
            $value = null;

            switch ($ref[0]) {
                case self::MACRO_VARIABLE:
                    if ($found = property_exists($vars, $ref[1])) {
                        $value = ake($vars, $ref[1]);
                    }

                    break;

                case self::MACRO_LOOKUP:
                    list($ref_type, $ref_table, $ref_column, $ref_criteria) = $ref;
                    // Lookup already queried data records
                    if (array_key_exists($ref_table, $records)) {
                        foreach (get_object_vars($records[$ref_table]) as $record) {
                            if (count(\array_diff_assoc($ref_criteria, (array) $record)) > 0) {
                                continue;
                            }
                            $value = ake($record, $ref_column);
                            $found = true;

                            break;
                        }
                    } else { // Fallback SQL query
                        $match = $this->dbi->table($ref_table)
                            ->limit(1)
                            ->find($ref_criteria, ['value' => $ref_column])
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
            $row->{$columnName} = $value;
        }

        return $row;
    }

    /**
     * @param array<mixed> $records
     */
    private function processDataObject(
        \stdClass $info,
        ?array &$records = null,
        ?\stdClass &$globalVars = null
    ): bool {
        if (($required_version = ake($info, 'version')) && !array_key_exists((int) $required_version, $this->getVersions(false, true))) {
            $this->log('Skipping section due to missing version '.$required_version);

            return false;
        }
        if (!is_array($records)) {
            $records = [];
        }
        if (!is_array($env = ake($info, 'env', [APPLICATION_ENV]))) {
            $env = [$env];
        }
        if (!in_array(APPLICATION_ENV, $env)) {
            return false;
        }
        // Set up any data object variables
        if (!$globalVars) {
            $globalVars = new \stdClass();
        }
        // We are setting these are global variables so store them un-prepared
        if (count(get_object_vars($vars = ake($info, 'vars', new \stdClass()))) > 0
            && !property_exists($info, 'table')) {
            /**
             * @var \stdClass $vars
             */
            $globalVars = object_merge($globalVars, $vars);
        }
        $vars = object_merge($globalVars, $vars);
        // Prepare variables  using the compiled list of variables
        if (count(get_object_vars($vars)) > 0) {
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
                if ($vars instanceof \stdClass) {
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
            if (true === $this->dbi->tableExists($table)) {
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
                if (($source = ake($info, 'source')) && ($remote_table = ake($info, 'table'))) {
                    $this->log('Syncing rows from remote source');
                    $rows = [];
                    $rowdata = '[]';
                    if ($hostURL = ake($source, 'hostURL')) {
                        if (!isset($source->syncKey)
                            && ($config = ake($source, 'config'))
                            && is_string($config)) {
                            $config = Adapter::getDefaultConfig($config);
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
                        $remoteStatement = $remoteDBI->table($remote_table)->sort($pkey);
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
                    foreach ($rows as $row_num => $row) {
                        if (count($col_diff = array_keys(array_diff_key((array) $row, $tableDef))) > 0) {
                            $this->log("Skipping missing columns in table '{$table}': ".implode(', ', $col_diff));
                            foreach ($col_diff as $key) {
                                unset($row->{$key});
                            }
                        }
                        // Prepare the row by resolving any macros
                        $row = $this->prepareRow($row, $records, $refs, $vars);
                        $do_diff = false;
                        /*
                         * If the primary key is in the record, find the record using only that field, then
                         * we will check for differences between the records
                         */
                        if (property_exists($row, $pkey)) {
                            $criteria = [$pkey => ake($row, $pkey)];
                            $do_diff = true;
                        // Otherwise, if there are search keys specified, base the search criteria on that
                        } elseif (property_exists($info, 'keys')) {
                            $criteria = [];
                            foreach ($info->keys as $key) {
                                $criteria[trim($key)] = ake($row, $key);
                            }
                            $do_diff = true;
                        // Otherwise, look for the record in it's entirity and only insert if it doesn't exist.
                        } else {
                            $criteria = (array) $row;
                            foreach ($criteria as &$criteria_item) {
                                if (is_array($criteria_item) || $criteria_item instanceof \stdClass) {
                                    $criteria_item = json_encode($criteria_item);
                                }
                            }
                        }

                        try {
                            // If this is an insert only row then move on because this row exists
                            if ($current = $this->dbi->table($table)->findOneRow($criteria)) {
                                if (!(ake($info, 'insertonly') || true !== $do_diff)) {
                                    $diff = array_diff_assoc_recursive(get_object_vars($row), $current->toArray());
                                    // If nothing has been added to the row, look for child arrays/objects to backwards analyse
                                    if (($changes = count($diff)) === 0) {
                                        foreach (get_object_vars($row) as $name => &$col) {
                                            if (!(is_array($col) || $col instanceof \stdClass)) {
                                                continue;
                                            }
                                            $changes += count(array_diff_assoc_recursive(ake($current, $name), $col));
                                        }
                                    }
                                    if ($changes > 0) {
                                        $pkey_value = ake($current, $pkey);
                                        $this->log("Updating record in table '{$table}' with {$pkey}={$pkey_value}");
                                        if (!$this->dbi->update($table, $this->fixRow($row, $tableDef), [$pkey => $pkey_value])) {
                                            throw new Datasync('Update failed: '.$this->dbi->errorInfo()[2]);
                                        }
                                        $current->extend($row);
                                    }
                                }
                                $current = $current->toArray();
                            } elseif (true !== ake($info, 'updateonly')) { // If this is an update only row then move on because this row does not exist
                                if (($pkey_value = $this->dbi->insert($table, $this->fixRow($row, $tableDef), $pkey)) == false) {
                                    throw new Datasync('Insert failed: '.$this->dbi->errorInfo()[2]);
                                }
                                $row->{$pkey} = $pkey_value;
                                $this->log("Inserted record into table '{$table}' with {$pkey}={$pkey_value}");
                                $current = (array) $row;
                            }
                        } catch (\Throwable $e) {
                            throw new Datasync('Row #'.$row_num.': '.$e->getMessage());
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
                    foreach ($sequences as $seq_name => $seq_value) {
                        $last_value = $this->dbi->query("SELECT last_value FROM {$seq_name};")->fetchColumn(0);
                        if ($last_value < $seq_value) {
                            if (!$this->dbi->query("SELECT setval('{$seq_name}', {$seq_value})")) {
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
                    if (!($source_table = ake($copy, 'sourceTable'))) {
                        throw new Datasync('Data sync copy command requires a source table.');
                    }
                    if (!($target_column = ake($copy, 'targetColumn'))) {
                        throw new Datasync('Data sync copy command requires a target index column.');
                    }
                    $map = (array) ake($copy, 'map', []);
                    if (0 === count($map)) {
                        throw new Datasync('Data sync copy command requires a valid column map.');
                    }
                    $set = (array) ake($copy, 'set', []);
                    $source_sql = "SELECT * FROM \"{$source_table}\" AS \"source\"";
                    if ($where = ake($copy, 'where')) {
                        $source_sql .= ' WHERE '.trim($where);
                    }
                    $source_query = $this->dbi->query($source_sql);
                    while ($row = $source_query->fetch()) {
                        $updates = new \stdClass();
                        foreach ($map as $target_col => $source_col) {
                            $updates->{$target_col} = ake($row, $source_col);
                        }
                        foreach ($set as $setKey => $setData) {
                            $updates->{$setKey} = $setData;
                        }
                        if (($vars = ake($copy, 'vars')) instanceof \stdClass) {
                            $vars = clone $vars;
                            $this->prepareRow($vars, $records, null, (object) $row);
                        }
                        $this->prepareRow($updates, $records, null, (object) array_merge((array) $vars, $row));
                        if (!property_exists($updates, $target_column)) {
                            throw new Datasync("Target column '{$target_column}' is not being updated in table '{$table}'!");
                        }
                        $exist_criteria = [$target_column => $updates->{$target_column}];
                        if ($this->dbi->table($table)->exists($exist_criteria)) {
                            $result = $this->dbi->table($table, 'target')->update($exist_criteria, array_diff_assoc(get_object_vars($updates), $exist_criteria));
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
        $fixed_row = [];
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
            $fixed_row[$name] = $col;
        }

        return $fixed_row;
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
            $fs_db = null;
            $this->log('Found DBI filesystem: '.$name);

            try {
                $settings->enhance(['dbi' => Adapter::getDefaultConfig(), 'initialise' => true]);
                $fs_db = new Adapter($settings['dbi']);
                if ($fs_db->tableExists('hz_file') && $fs_db->tableExists('hz_file_chunk')) {
                    continue;
                }
                if (true !== $settings['initialise']) {
                    throw new Exception\FileSystem($name.' requires initialisation but initialise is disabled!');
                }
                $schema = realpath(__DIR__.str_repeat(DIRECTORY_SEPARATOR.'..', 2)
                    .DIRECTORY_SEPARATOR.'libs'
                    .DIRECTORY_SEPARATOR.'dbi'
                    .DIRECTORY_SEPARATOR.'schema.json');
                $manager = $fs_db->getSchemaManager();
                $this->log('Initialising DBI filesystem: '.$name);
                if (!$manager->applySchemaFromFile($schema)) {
                    throw new Exception\FileSystem('Unable to configure DBI filesystem schema!');
                }
                // Look for the old tables and if they exists, do an upgrade!
                if ($fs_db->tableExists('file') && $fs_db->tableExists('file_chunk')) {
                    if (!$fs_db->table('hz_file_chunk')->insert($fs_db->table('file_chunk')->select('id', null, 'n', 'data'))) {
                        throw $fs_db->errorException();
                    }
                    if (!$fs_db->table('hz_file')->insert($fs_db->table('file')->find(['kind' => 'dir'], ['id', 'kind', ['parent' => 'unnest(parents)'], null, 'filename', 'created_on', 'modified_on', 'length', 'mime_type', 'md5', 'owner', 'group', 'mode', 'metadata']))) {
                        throw $fs_db->errorException();
                    }
                    $fs_db->driver->repair();
                    if (!$fs_db->query("INSERT INTO hz_file (kind, parent, start_chunk, filename, created_on, modified_on, length, mime_type, md5, owner, \"group\", mode, metadata) SELECT kind, unnest(parents) as parent, (SELECT fc.id FROM file_chunk fc WHERE fc.file_id=f.id), filename, created_on, modified_on, length, mime_type, md5, owner, \"group\", mode, metadata FROM file f WHERE kind = 'file'")) {
                        throw $fs_db->errorException();
                    }
                }
            } catch (\Throwable $e) {
                $this->log($e->getMessage());

                continue;
            }
        }

        return true;
    }
}
