<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Enum;

enum Status: int
{
    // Service Status Codes
    case STARTING = 0;
    case RECONNECT = 1;
    case CONNECT = 2;
    case INIT = 3;
    case READY = 4;
    case RUNNING = 5;
    case SLEEP = 6;
    case STOPPING = 7;
    case STOPPED = 8;
    case ERROR = -1;
}
