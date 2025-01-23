<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class ReplayCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('replay')
            ->setDescription('Replay the database schema.')
            ->setHelp('This command will replay the database schema to a specific version.')
            ->addArgument('version', 'The version to rollback to.')
            ->addOption('test', 't', 'Test the migration without actually applying it.')
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
        if ($manager->replay(
            $version,
            $input->getOption('test') ?? false
        )) {
            $code = 0;
        }

        return 0;
    }
}
