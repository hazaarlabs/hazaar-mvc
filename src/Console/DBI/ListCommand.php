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
            ->addOption('missing', null, 'List missing versions only')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $applied = $input->getOption('applied') ?? false;
        $missing = $input->getOption('missing') ?? false;
        $versions = [];
        if (true === $applied) {
            $version = $manager->getAppliedVersions();
        } elseif (true === $missing) {
            $version = $manager->getMissingVersions();
        } else {
            $versions = $manager->getVersions();
        }
        if (count($versions) > 0) {
            foreach ($versions as $version) {
                $output->write($version.PHP_EOL);
            }
        } else {
            $output->write('No schema versions found!'.PHP_EOL);
        }

        return 0;
    }
}
