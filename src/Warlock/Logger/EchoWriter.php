<?php

namespace Hazaar\Warlock\Logger;

use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Interface\LogWriter;

/**
 * EchoWriter class for writing log messages to the standard output.
 * This class extends the Logger class and overrides the write method
 * to output log messages directly to the console.
 */
class EchoWriter implements LogWriter
{
    /**
     * Writes a log message to the standard output.
     */
    public function write(string $message, ?LogLevel $level = null, ?string $prefix = null): void
    {
        echo date('Y-m-d H:i:s')
            .' | '.$level->color(sprintf('%-7s', $prefix ?? 'WARLOCK'))
            .' | '.$level->color(sprintf('%-'.$level::pad().'s', $level->name))
            .' | '.$level->color($message)."\n";
    }
}
