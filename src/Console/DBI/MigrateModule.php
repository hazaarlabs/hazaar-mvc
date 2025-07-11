<?php

namespace Hazaar\Console\DBI;

use Hazaar\Application;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;
use Hazaar\Loader;

class MigrateModule extends Module
{
    public function prepareApp(Input $input, Output $output): int
    {
        $applicationPath = $input->getOption('path');
        if (!$applicationPath || '/' !== substr(trim($applicationPath), 0, 1)) {
            $searchResult = Application::findAppPath($applicationPath);
            if (null === $searchResult) {
                $output->write('Application path not found: '.$applicationPath.PHP_EOL);

                return 1;
            }
            $applicationPath = $searchResult;
            Loader::createInstance($applicationPath);
        }

        return 0;
    }

    protected function configure(): void
    {
        $this->application->registerMethod([$this, 'prepareApp']);
        $this->addGlobalOption('path', 'p', 'The path to the application directory.');
        $this->addCommand('migrate')
            ->setDescription('Migrate the database schema')
            ->setHelp('This command allows you to migrate the database schema to a specific version.')
            ->addArgument('version', 'The version to migrate to.')
            ->addOption('force_sync', 'f', 'Force a sync of the database schema.')
            ->addOption('keep_tables', 'k', 'Keep the tables in the database.')
            ->addOption('force_init', null, 'Force the database to be re-initialised.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerLogHandler(function ($message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        if ($version = $input->getArgument('version')) {
            settype($version, 'int');
        }
        if ($input->getOption('force_init') ?? false) {
            $output->write('WARNING: Forcing full database re-initialisation.'.PHP_EOL);
            $output->write('THIS WILL DELETE ALL DATA!!!'.PHP_EOL);
            $output->write('IF YOU DO NOT WANT TO DO THIS, YOU HAVE 10 SECONDS TO CANCEL'.PHP_EOL);
            sleep(10);
            $output->write('DELETING YOUR DATA!!!  YOU WERE WARNED!!!'.PHP_EOL);
            $manager->deleteEverything();
        }
        if (!$manager->migrate($version)) {
            return 1;
        }
        if (!$manager->sync(null, $input->getOption('force_sync') ?? false)) {
            return 2;
        }

        return 0;
    }
}
