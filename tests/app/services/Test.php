<?php

namespace App\Service;

use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Service;

/**
 * @internal
 */
class Test extends Service
{
    public string $type = 'test';

    public function doTheThing(): void
    {
        $this->log('Executing test service', LogLevel::INFO);
        $this->log('Service ID: '.$this->id, LogLevel::INFO);
        $this->log('Service Type: '.$this->type, LogLevel::INFO);
    }

    public function init(): bool
    {
        $this->log->write('Initializing test service', LogLevel::INFO);

        return true;
    }

    public function run(): void
    {
        $this->log->write($this->config['message'] ?? 'Test service is running', LogLevel::INFO);
        $this->sleep(5);
    }
}
