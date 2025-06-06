<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Module;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class ReplayModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('replay')
            ->setDescription('Replay the database schema.')
            ->setHelp('This command will replay the database schema to a specific version.')
            ->addArgument('version', 'The version to rollback to.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerLogHandler(function ($message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        if ($version = $input->getArgument('version')) {
            settype($version, 'int');
        }
        if (!$manager->replay($version)) {
            return 1;
        }

        return 0;
    }
}
