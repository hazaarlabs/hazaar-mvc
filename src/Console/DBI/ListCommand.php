<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('list')
            ->setDescription('List database versions')
            ->setHelp('This command allows you to list the database schema versions.')
            ->addOption('applied', null, 'List applied versions only')
            ->addOption('env', 'e', 'The environment to use.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        $applied = $input->getOption('applied') ?? false;
        $versions = $manager->getVersions(false, $applied);
        foreach ($versions as $version => $comment) {
            $output->write(str_pad((string) $version, 10, ' ', STR_PAD_RIGHT)." {$comment}".PHP_EOL);
        }

        return 0;
    }
}
