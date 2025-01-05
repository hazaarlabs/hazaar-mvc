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
            ->addOption('env', 'e', 'The environment to use.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        if ($manager->checkpoint()) {
            return 0;
        }

        return 1;
    }
}
