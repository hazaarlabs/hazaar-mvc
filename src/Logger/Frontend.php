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
    private static array $messageBuffer = [];

    /**
     * @param array<mixed> $backendOptions
     */
    public function __construct(int|string $level, ?string $backend = null, array $backendOptions = [])
    {
        if (!$backend) {
            $backend = 'file';
        }
        if ('database' == strtolower($backend)) {
            $backend = 'Database';
        }
        $backendClass = 'Hazaar\Logger\Backend\\'.ucfirst($backend);
        if (!class_exists($backendClass)) {
            throw new Exception\NoBackend();
        }
        $this->backend = new $backendClass($backendOptions);
        if (is_numeric($level)) {
            $this->level = $level;
        } elseif (($this->level = $this->backend->getLogLevelId($level)) === 0) {
            $this->level = E_ERROR;
        }
        $buf = Frontend::$messageBuffer;
        if (count($buf) > 0) {
            foreach ($buf as $msg) {
                $this->writeLog($msg[0], $msg[1]);
            }
        }
        Frontend::$messageBuffer = [];
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
            Frontend::$messageBuffer[] = [
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
                $message = 'OBJECT DUMP:'.PHP_EOL.preg_replace('/\n/', PHP_EOL, print_r($message, true));
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
