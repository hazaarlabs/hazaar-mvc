<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Enum;

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
        return 7;
    }

    public function color(string $text): string
    {
        return match ($this) {
            self::NONE => "\033[0m",
            self::INFO => "\033[0;32m",
            self::WARN => "\033[0;33m",
            self::ERROR => "\033[0;31m",
            self::NOTICE => "\033[0;34m",
            self::DEBUG => "\033[0;35m",
            self::DECODE => "\033[0;36m",
            self::DECODE2 => "\033[1;36m",
        }.$text."\033[0m";
    }

    public static function fromString(string $level): self
    {
        return match (strtolower($level)) {
            'info' => self::INFO,
            'warn' => self::WARN,
            'error' => self::ERROR,
            'notice' => self::NOTICE,
            'debug' => self::DEBUG,
            'decode' => self::DECODE,
            'decode2' => self::DECODE2,
            default => self::NONE,
        };
    }
}
