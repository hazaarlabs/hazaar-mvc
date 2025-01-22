<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Migrate the database schema')
            ->setHelp('This command allows you to migrate the database schema to a specific version.')
            ->addArgument('version', 'The version to migrate to.')
            ->addOption('test', 't', 'Test the migration without actually applying it.')
            ->addOption('force_sync', 'f', 'Force a sync of the database schema.')
            ->addOption('keep_tables', 'k', 'Keep the tables in the database.')
            ->addOption('force_init', null, 'Force the database to be re-initialised.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerOutputHandler(function ($time, $message) use ($output) {
            $output->write(date('H:i:s', (int) round($time)).' '.$message.PHP_EOL);
        });
        if ($version = $input->getArgument('version')) {
            settype($version, 'int');
        }
        if ($manager->migrate(
            $version,
            $input->getOption('force_sync') ?? false,
            $input->getOption('test') ?? false,
            $input->getOption('keep_tables') ?? false,
            $input->getOption('force_init') ?? false
        )) {
            $code = 0;
        }

        return 0;
    }
}
