<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class CurrentCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('current')
            ->setDescription('Get the current database schema version.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $comment = ake($manager->getVersions(), $version = $manager->getVersion());
        $output->write(str_pad((string) $version, 10, ' ', STR_PAD_RIGHT)." {$comment}".PHP_EOL);

        return 0;
    }
}
