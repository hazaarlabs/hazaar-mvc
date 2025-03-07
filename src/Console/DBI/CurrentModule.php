<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class CurrentModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('current')
            ->setDescription('Get the current database schema version.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $version = $manager->getCurrentVersion();
        if (null === $version) {
            $output->write('No schema version found.'.PHP_EOL);
        } else {
            $output->write($version.PHP_EOL);
        }

        return 0;
    }
}
