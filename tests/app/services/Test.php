<?php

namespace App\Service;

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
        $this->log('Executing test service', LogLevel::INFO);
        $this->log('Service ID: '.$this->id, LogLevel::INFO);
        $this->log('Service Type: '.$this->type, LogLevel::INFO);
    }
}
