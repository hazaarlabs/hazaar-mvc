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
            ->addOption('env', 'e', 'The environment to use.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        $comment = ake($manager->getVersions(), $version = $manager->getVersion());
        $output->write(str_pad((string) $version, 10, ' ', STR_PAD_RIGHT)." {$comment}".PHP_EOL);

        return 0;
    }
}
