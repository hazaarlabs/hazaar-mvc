<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema;

use Hazaar\Application;
use Hazaar\Application\Request\CLI;
use Hazaar\DBI\Adapter;

class Tool
{
    public static function run(Application $application): int
    {
        if (!$application->request instanceof CLI) {
            return 255;
        }
        $application->request->setOptions([
            'help' => ['h', 'help', null, 'Display this help message.'],
            'test' => ['t', 'test', null, 'Enable test mode.  Any write actions will be simulated but not applied to the database.'],
            'force_sync' => ['f', 'force-sync', null, 'Force the data sync after migration completes, even if no changes are made.', 'migrate'],
            'keep_tables' => ['k', 'keep-tables', null, 'Keep unmanaged tables when initialising the database.', 'migrate'],
            'force_init' => [null, 'force-reinitialise', null, 'Force reinitialise the database.', 'migrate'],
            'applied' => ['a', 'applied', null, 'Only list versions applied to the current schema.', 'list'],
        ]);
        $application->request->setCommands([
            'list' => ['List the available schema versions.'],
            'current' => ['Display the current schema version.'],
            'migrate' => ['Migrate the database to a specific version (default: latest).', 'version'],
            'replay' => ['Replay a single migration.', 'version'],
            'rollback' => ['[EXPERIMENTAL] Rollback a migration including it\'s dependencies.', 'version'],
            'snapshot' => ['Snapshot the database schema. (default: New Snapshot)', 'comment'],
            'sync' => ['Synchronise database data files.'],
            'schema' => ['Display the current database schema.'],
            'checkpoint' => ['Checkpoint database migrations.  Creates a new migration file with consolidated changed.'],
        ]);
        if (!($command = $application->request->getCommand($command_args))) {
            $application->request->showHelp();

            return 1;
        }
        $options = $application->request->getOptions();
        $code = 1;

        try {
            $manager = Adapter::getSchemaManagerInstance(null, function ($time, $msg) {
                echo date('Y-m-d H:i:s', (int) $time).' - '.$msg."\n";
            });

            switch ($command) {
                case 'list':
                    $versions = $manager->getVersions(false, ake($options, 'applied', false));
                    foreach ($versions as $version => $comment) {
                        echo str_pad((string) $version, 10, ' ', STR_PAD_RIGHT)." {$comment}\n";
                    }

                    break;

                case 'current':
                    $comment = ake($manager->getVersions(), $version = $manager->getVersion());
                    echo str_pad((string) $version, 10, ' ', STR_PAD_RIGHT)." {$comment}\n";

                    break;

                case 'migrate':
                    if ($version = ake($command_args, 0)) {
                        settype($version, 'int');
                    }
                    if ($manager->migrate(
                        $version,
                        ake($options, 'force_sync', false),
                        ake($options, 'test', false),
                        ake($options, 'keep_tables', false),
                        ake($options, 'force_init', false)
                    )) {
                        $code = 0;
                    }

                    break;

                case 'replay':
                    if ($version = ake($command_args, 0)) {
                        settype($version, 'int');
                    }
                    if ($manager->migrateReplay(
                        $version,
                        ake($options, 'test', false)
                    )) {
                        $code = 0;
                    }

                    break;

                case 'rollback':
                    if ($version = ake($command_args, 0)) {
                        settype($version, 'int');
                    }
                    if ($manager->rollback(
                        $version,
                        ake($options, 'test', false)
                    )) {
                        $code = 0;
                    }

                    break;

                case 'snapshot':
                    $comment = trim(implode(' ', $command_args)) ?: 'New Snapshot';
                    if ($manager->snapshot($comment, ake($options, 'test'))) {
                        $code = 0;
                    }

                    break;

                case 'sync':
                    $data = null;
                    if ($sync_file = ake($command_args, 0)) {
                        $sync_file = realpath(APPLICATION_PATH.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.$sync_file);
                        if (!($sync_file && file_exists($sync_file))) {
                            throw new \Exception('Unable to sync.  File not found: '.realpath($sync_file));
                        }
                        if (!($data = json_decode(file_get_contents($sync_file)))) {
                            throw new \Exception('Unable to sync.  File is not a valid JSON file.');
                        }
                    }
                    if ($manager->syncData($data, ake($options, 'test'), true)) {
                        $code = 0;
                    }

                    break;

                case 'schema':
                    if ($schema = $manager->getSchema()) {
                        echo json_encode($schema, JSON_PRETTY_PRINT);
                        $code = 0;
                    }

                    break;

                case 'checkpoint':
                    if ($manager->checkpoint()) {
                        $code = 0;
                    }

                    break;
            }
        } catch (\Throwable $e) {
            echo $e->getMessage()."\n";
            $code = $e->getCode();
        }

        return $code;
    }
}
