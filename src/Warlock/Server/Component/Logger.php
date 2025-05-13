<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Component;

use Hazaar\Warlock\Server\Enum\LogLevel;

/**
 * Logger class for handling logging operations with different log levels.
 *
 * This class provides methods to write log messages with specified log levels,
 * set the logging level, and retrieve the current logging level.
 */
class Logger
{
    private LogLevel $level = LogLevel::INFO;
    private string $prefix = 'WARLOCK';

    public function __construct(int|string $level = 0)
    {
        if (is_string($level)) {
            $level = constant(LogLevel::class.'::'.strtoupper($level));
        } else {
            $level = LogLevel::from($level);
        }
        $this->setLevel($level);
    }

    /**
     * Writes a log message with a specified log level.
     *
     * @param string   $message The message to log. It can be an array, an instance of stdClass, or a string.
     * @param LogLevel $level   The log level of the message. Defaults to LogLevel::INFO.
     */
    public function write(string $message, LogLevel $level = LogLevel::INFO): void
    {
        if ($level > $this->level) {
            return;
        }
        echo date('Y-m-d H:i:s')
            .' | '.$level->color(sprintf('%-6s', $this->prefix))
            .' | '.$level->color(sprintf('%-'.$level::pad().'s', $level->name))
            .' | '.$level->color($message)."\n";
    }

    /**
     * Sets the logging level for the logger.
     *
     * @param null|LogLevel $level The logging level to set. Defaults to LogLevel::INFO if not provided.
     */
    public function setLevel(?LogLevel $level = LogLevel::INFO): void
    {
        $this->level = $level;
    }

    /**
     * Get the current logging level.
     *
     * @return LogLevel the current logging level
     */
    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    /**
     * Set a prefix for the logger.
     *
     * @param string $prefix the prefix to set
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = strtoupper($prefix);
    }

    /**
     * Get the current prefix of the logger.
     *
     * @return string the current prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
