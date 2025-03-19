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
        if ($syncFile = $input->getArgument('sync_file')) {
            $syncFile = Loader::getFilePath(FilePath::APPLICATION, DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.$syncFile);
            if (!($syncFile && file_exists($syncFile))) {
                throw new \Exception('Unable to sync.  File not found: '.realpath($syncFile));
            }
            if (!($data = json_decode(file_get_contents($syncFile)))) {
                throw new \Exception('Unable to sync.  File is not a valid JSON file.');
            }
        }
        if (!$manager->sync($data, true)) {
            return 1;
        }

        return 0;
    }
}
