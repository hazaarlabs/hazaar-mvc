<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class SchemaModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('schema')
            ->setDescription('Display the current database schema.')
            ->addOption('all', 'a', 'Display the entire schema instead of just the applied schema.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $all = $input->getOption('all') ?: false;
        $manager = Adapter::getSchemaManagerInstance();
        $schema = $manager->getSchema($all);
        if (!$schema) {
            return 1;
        }
        $output->write(json_encode($schema, JSON_PRETTY_PRINT).PHP_EOL);

        return 0;
    }
}
