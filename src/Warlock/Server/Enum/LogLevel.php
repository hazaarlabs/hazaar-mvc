<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Enum;

enum LogLevel: int
{
    case NONE = 0;
    case INFO = 1;
    case WARN = 2;
    case ERROR = 3;
    case NOTICE = 4;
    case DEBUG = 5;
    case DECODE = 6;
    case DECODE2 = 7;

    /**
     * Returns the padding value for to use for the log level.
     *
     * This is used to pad the log level name to a fixed width when writing log messages.  It is
     * defined here so that it can be easily changed in one place.
     *
     * @return int the padding value, which is 5
     */
    public static function pad(): int
    {
        return 6;
    }
}
