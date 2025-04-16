<?php

namespace Hazaar\Console\Modules;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\Util\GeoData;
use Hazaar\Util\Str;

class UtilsModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('geo', [$this, 'cacheGeodataFile'])
            ->setDescription('Cache the geodata database file')
        ;
    }

    protected function cacheGeodataFile(Input $input, Output $output): int
    {
        try {
            $output->write('<fg=green>Fetching geodata database file...</>'.PHP_EOL);
            $dbFile = GeoData::fetchDBFile();
            $output->write('<fg=green>GeoData database file cached successfully.</>'.PHP_EOL);
            $output->write('<fg=green>Database file: '.$dbFile.'</>'.PHP_EOL);
            $output->write('<fg=green>Database file size: '.Str::fromBytes($dbFile->size()).' bytes</>'.PHP_EOL);
        } catch (\Exception $e) {
            throw new \Exception('Failed to cache the geodata database file: '.$e->getMessage());
        }

        return 0;
    }
}
