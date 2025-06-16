<?php

namespace Hazaar\Warlock\Interface;

use Hazaar\Warlock\Enum\LogLevel;

/**
 * LogWriter interface for writing log messages.
 *
 * This interface defines the contract for log writers that can be used
 * to output log messages in different formats or to different destinations.
 */
interface LogWriter
{
    /**
     * Writes a log message to the output.
     */
    public function write(string $message, ?LogLevel $level = null, ?string $prefix = null): void;
}
