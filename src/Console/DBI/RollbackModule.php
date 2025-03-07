<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class RollbackModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('rollback')
            ->setDescription('Rollback the database schema.')
            ->setHelp('This command will rollback the database schema to a specific version.')
            ->addArgument('version', 'The version to rollback to.')
            ->addOption('test', 't', 'Enable test mode.  Any write actions will be simulated but not applied to the database.')
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
        if (!$manager->rollback($version)) {
            return 1;
        }

        return 0;
    }
}
