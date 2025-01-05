<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class ReplayCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('replay')
            ->setDescription('Replay the database schema.')
            ->setHelp('This command will replay the database schema to a specific version.')
            ->addArgument('version', 'The version to rollback to.')
            ->addOption('env', 'e', 'The environment to use.')
            ->addOption('test', 't', 'Test the migration without actually applying it.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        if ($version = $input->getArgument('version')) {
            settype($version, 'int');
        }
        if ($manager->migrateReplay(
            $version,
            $input->getOption('test') ?? false
        )) {
            $code = 0;
        }

        return 0;
    }
}
