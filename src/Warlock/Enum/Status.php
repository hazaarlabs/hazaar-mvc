<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Enum;

enum Status: int
{
    // Service Status Codes
    case NONE = 0;
    case INIT = 1;
    case READY = 2;
    case RUNNING = 3;
    case SLEEP = 4;
    case STOPPING = 5;
    case STOPPED = 6;
    case ERROR = -1;

    public function toString(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::INIT => 'Initializing',
            self::READY => 'Ready',
            self::RUNNING => 'Running',
            self::SLEEP => 'Sleeping',
            self::STOPPING => 'Stopping',
            self::STOPPED => 'Stopped',
            self::ERROR => 'Error',
        };
    }
}
