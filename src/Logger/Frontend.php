<?php

declare(strict_types=1);

namespace Hazaar\Logger;

class Frontend
{
    private int $level = E_ERROR;
    private Backend $backend;
    private static ?Frontend $logger = null;

    /**
     * @var array<mixed>
     */
    private static array $message_buffer = [];

    /**
     * @param array<mixed> $backend_options
     */
    public function __construct(int|string $level, ?string $backend = null, array $backend_options = [])
    {
        if (!$backend) {
            $backend = 'file';
        }
        if ('database' == strtolower($backend)) {
            $backend = 'Database';
        }
        $backend_class = 'Hazaar\Logger\Backend\\'.ucfirst($backend);
        if (!class_exists($backend_class)) {
            throw new Exception\NoBackend();
        }
        $this->backend = new $backend_class($backend_options);
        if (is_numeric($level)) {
            $this->level = $level;
        } elseif (($this->level = $this->backend->getLogLevelId($level)) === false) {
            $this->level = E_ERROR;
        }
        $buf = Frontend::$message_buffer;
        if (count($buf) > 0) {
            foreach ($buf as $msg) {
                $this->writeLog($msg[0], $msg[1]);
            }
        }
        Frontend::$message_buffer = [];
    }

    /**
     * @param array<mixed> $config
     */
    public static function initialise(array $config): void
    {
        if (true !== $config['enable']) {
            return;
        }
        Frontend::$logger = new Frontend($config['level'], $config['backend'], $config['options']);
        eval('class log extends \Hazaar\Logger\Frontend{};');
    }

    public static function destroy(): void
    {
        if (Frontend::$logger instanceof Frontend) {
            Frontend::$logger->close();
        }
    }

    public static function write(string $message, int $level = E_NOTICE, ?string $tag = null): void
    {
        if (Frontend::$logger instanceof Frontend) {
            Frontend::$logger->writeLog($message, $level, $tag);
        } else {
            Frontend::$message_buffer[] = [
                $message,
                $level,
                $tag,
            ];
        }
    }

    /**
     * Log an ERROR message.
     */
    public static function e(string $message, ?string $tag = null): void
    {
        Frontend::write($message, LOG_ERR, $tag);
    }

    /**
     * Log a WARNING message.
     */
    public static function w(string $message, ?string $tag = null): void
    {
        Frontend::write($message, LOG_WARNING, $tag);
    }

    /**
     * Log a NOTICE message.
     */
    public static function n(string $message, ?string $tag = null): void
    {
        Frontend::write($message, LOG_NOTICE, $tag);
    }

    /**
     * Log a INFO message.
     */
    public static function i(string $message, ?string $tag = null): void
    {
        Frontend::write($message, LOG_INFO, $tag);
    }

    /**
     * Log a DEBUG message.
     */
    public static function d(string $message, ?string $tag = null): void
    {
        Frontend::write($message, LOG_DEBUG, $tag);
    }

    public static function trace(): void
    {
        if (Frontend::$logger instanceof Frontend) {
            Frontend::$logger->backtrace();
        }
    }

    /**
     * @param array<mixed>|object|string $message
     */
    public function writeLog(array|object|string $message, int $level = E_NOTICE, ?string $tag = null): void
    {
        if (!($level <= $this->level)) {
            return;
        }
        if (!$this->backend->can('write_objects')) {
            if (is_array($message) || is_object($message)) {
                $message = 'OBJECT DUMP:'.LINE_BREAK.preg_replace('/\n/', LINE_BREAK, print_r($message, true));
            }
        }
        $this->backend->write($message, $level, $tag);
    }

    public function backtrace(): void
    {
        if ($this->backend->can('write_trace')) {
            $this->backend->trace();
        }
    }

    public function close(): void
    {
        $this->backend->postRun();
    }
}
