<?php

namespace App\Services;

use Hazaar\Warlock\Agent\Container;
use Hazaar\Warlock\Enum\LogLevel;

/**
 * @internal
 */
class Test extends Container
{
    public string $type = 'test';

    public function doTheThing(): void
    {
        $this->log->write('Executing test service', LogLevel::INFO);
        $this->log->write('Service ID: '.$this->id, LogLevel::INFO);
        $this->log->write('Service Type: '.$this->type, LogLevel::INFO);
    }
}
