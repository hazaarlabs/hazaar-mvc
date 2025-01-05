<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class SchemaCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('schema')
            ->setDescription('Display the current database schema.')
            ->addOption('env', 'e', 'The environment to use.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        if ($schema = $manager->getSchema()) {
            $output->write(json_encode($schema, JSON_PRETTY_PRINT));

            return 0;
        }

        return 1;
    }
}
