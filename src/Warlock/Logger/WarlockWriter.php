<?php

namespace Hazaar\Warlock\Logger;

use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Interface\LogWriter;
use Hazaar\Warlock\Process;

class WarlockWriter implements LogWriter
{
    private Process $process;
    private string $prefix = 'WARLOCK';

    public function __construct(Process $process)
    {
        $this->process = $process;
    }

    /**
     * Writes a log message to the standard output.
     *
     * @param string $message the log message to write
     */
    public function write(string $message, ?LogLevel $level = null, ?string $prefix = null): void
    {
        $this->process->log($message, $level, $prefix ?? $this->prefix);
    }

    /**
     * Sets the prefix for the log messages.
     *
     * @param string $prefix the prefix to set for log messages
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }
}
