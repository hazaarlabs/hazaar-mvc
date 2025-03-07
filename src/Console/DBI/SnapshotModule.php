<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Module;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class SnapshotModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('snapshot')
            ->setDescription('Snapshot the current database schema.')
            ->setHelp('This command will create a snapshot of the current database schema and generate a new migration file.')
            ->addArgument('comment', 'The comment to add to the snapshot.')
            ->addOption('test', 't', 'Enable test mode.  Any write actions will be simulated but not applied to the database.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerLogHandler(function ($message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        $comment = $input->getArgument('comment') ?: 'New Snapshot';
        if (!$manager->snapshot($comment, $input->getOption('test') ?? false)) {
            return 1;
        }

        return 0;
    }
}
