<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Interface\LogWriter;
use Hazaar\Warlock\Logger\EchoWriter;

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
    private LogWriter $writer;

    /**
     * Allows the logger to be temporarily silent, meaning it will not output any log messages, but
     * the log level will still be respected.
     *
     * This is useful for scenarios where you want to disable logging without changing the log level.
     */
    private bool $silent = false;

    public function __construct(LogLevel $level = LogLevel::INFO, ?LogWriter $writer = null)
    {
        $this->setLevel($level);
        $this->setWriter($writer ?? new EchoWriter());
    }

    public function getNewChildLogger(string $prefix): self
    {
        $logger = new self($this->level);
        $logger->setPrefix($prefix);
        $logger->setSilent($this->silent);

        return $logger;
    }

    /**
     * Writes a log message with a specified log level.
     *
     * @param string   $message The message to log. It can be an array, an instance of stdClass, or a string.
     * @param LogLevel $level   The log level of the message. Defaults to LogLevel::INFO.
     */
    public function write(string $message, LogLevel $level = LogLevel::INFO, ?string $prefix = null): void
    {
        if ($this->silent || $level->value > $this->level->value) {
            return;
        }
        $this->writer->write($message, $level, $prefix ?? $this->prefix);
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

    /**
     * Sets the silent mode for the logger.
     *
     * When silent mode is enabled, the logger will suppress output without changing the log level.
     *
     * @param bool $silent whether to enable silent mode
     */
    public function setSilent(bool $silent): void
    {
        $this->silent = $silent;
    }

    /**
     * Sets the log writer instance to be used by the logger.
     *
     * @param LogWriter $writer the log writer instance to set
     */
    public function setWriter(LogWriter $writer): void
    {
        $this->writer = $writer;
    }
}
