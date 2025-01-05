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
            ->addOption('env', 'e', 'The environment to use.')
            ->addOption('test', 't', 'Enable test mode.  Any write actions will be simulated but not applied to the database.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
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
