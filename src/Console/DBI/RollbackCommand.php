<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class RollbackCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('rollback')
            ->setDescription('Rollback the database schema.')
            ->setHelp('This command will rollback the database schema to a specific version.')
            ->addArgument('version', 'The version to rollback to.')
            ->addOption('test', 't', 'Enable test mode.  Any write actions will be simulated but not applied to the database.')
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
        if ($manager->rollback(
            $version,
            $input->getOption('test') ?? false
        )) {
            $code = 0;
        }

        return 0;
    }
}
