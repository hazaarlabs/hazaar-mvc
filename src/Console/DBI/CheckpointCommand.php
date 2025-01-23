<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class CheckpointCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('checkpoint')
            ->setDescription('Checkpoints the database schema.')
            ->setHelp('This command will checkpoint the database schema by consolidating all changes into a single migration file.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerLogHandler(function ($message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        if ($manager->checkpoint()) {
            return 0;
        }

        return 1;
    }
}
