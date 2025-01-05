<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class SnapshotCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('snapshot')
            ->setDescription('Snapshot the current database schema.')
            ->setHelp('This command will create a snapshot of the current database schema and generate a new migration file.')
            ->addArgument('comment', 'The comment to add to the snapshot.')
            ->addOption('env', 'e', 'The environment to use.')
            ->addOption('test', 't', 'Enable test mode.  Any write actions will be simulated but not applied to the database.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        $comment = $input->getArgument('comment') ?: 'New Snapshot';
        if ($manager->snapshot($comment, $input->getOption('test') ?? false)) {
            return 0;
        }

        return 1;
    }
}
