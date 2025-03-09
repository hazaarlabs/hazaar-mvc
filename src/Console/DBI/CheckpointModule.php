<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Module;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class CheckpointModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('checkpoint')
            ->setDescription('Checkpoints the database schema.')
            ->setHelp('This command will checkpoint the database schema by consolidating all changes into a single migration file.')
            ->addArgument('comment', 'Add a comment to the checkpoint')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerLogHandler(function ($message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        if (!$manager->checkpoint($input->getArgument('comment'))) {
            return 1;
        }

        return 0;
    }
}
