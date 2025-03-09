<?php

namespace Hazaar\Console\DBI;

use Hazaar\Application\FilePath;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\DBI\Adapter;
use Hazaar\Loader;

class SyncModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('sync')
            ->setDescription('Sync the database data files')
            ->setHelp('This command will sync the database with the data files.')
            ->addArgument('sync_file', 'The file to sync with the database.')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $manager = Adapter::getSchemaManagerInstance();
        $manager->registerLogHandler(function ($message) use ($output) {
            $output->write($message.PHP_EOL);
        });
        $data = null;
        if ($sync_file = $input->getArgument('sync_file')) {
            $sync_file = Loader::getFilePath(FilePath::APPLICATION, DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.$sync_file);
            if (!($sync_file && file_exists($sync_file))) {
                throw new \Exception('Unable to sync.  File not found: '.realpath($sync_file));
            }
            if (!($data = json_decode(file_get_contents($sync_file)))) {
                throw new \Exception('Unable to sync.  File is not a valid JSON file.');
            }
        }
        if (!$manager->sync($data, true)) {
            return 1;
        }

        return 0;
    }
}
