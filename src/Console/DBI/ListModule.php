<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class ListModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('list')
            ->setDescription('List database versions')
            ->setHelp('This command allows you to list the database schema versions.')
            ->addOption('applied', null, 'List applied versions only')
            ->addOption('missing', null, 'List missing versions only')
            ->addOption('all', null, 'List all versions')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $versions = [];
        if (true === $input->getOption('applied')) {
            $versions = $manager->getAppliedVersions();
        } elseif (true === $input->getOption('missing')) {
            $versions = $manager->getMissingVersions();
        } else {
            $versions = $manager->getVersions($input->getOption('all'));
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
