<?php

namespace Hazaar\Console\DBI;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;

class SyncCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('sync')
            ->setDescription('Sync the database data files')
            ->setHelp('This command will sync the database with the data files.')
            ->addArgument('sync_file', 'The file to sync with the database.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? getenv('APPLICATION_ENV') ?: 'development';
        $manager = Adapter::getSchemaManagerInstance($env);
        $data = null;
        if ($sync_file = $input->getArgument('sync_file')) {
            $sync_file = realpath(APPLICATION_PATH.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.$sync_file);
            if (!($sync_file && file_exists($sync_file))) {
                throw new \Exception('Unable to sync.  File not found: '.realpath($sync_file));
            }
            if (!($data = json_decode(file_get_contents($sync_file)))) {
                throw new \Exception('Unable to sync.  File is not a valid JSON file.');
            }
        }
        if ($manager->syncData($data, $input->getOption('test') ?? false, true)) {
            $code = 0;
        }

        return 0;
    }
}
