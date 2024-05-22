<?php

declare(strict_types=1);

namespace Hazaar\Logger;

use Hazaar\Map;

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
     * @param array<mixed>|Map $backend_options
     */
    public function __construct(int|string $level, ?string $backend = null, array|Map $backend_options = [])
    {
        if (!$backend) {
            $backend = 'file';
        }
        if ('mongodb' == strtolower($backend)) {
            $backend = 'MongoDB';
        }
        if ('database' == strtolower($backend)) {
            $backend = 'Database';
        }
        $backend_class = 'Hazaar\\Logger\\Backend\\'.ucfirst($backend);
        if (!class_exists($backend_class)) {
            throw new Exception\NoBackend();
        }
        $this->backend = new $backend_class(Map::_($backend_options));
        if (is_numeric($level)) {
            $this->level = $level;
        } elseif (($this->level = $this->backend->getLogLevelId($level)) === false) {
            $this->level = E_ERROR;
        }
        $buf = Frontend::$message_buffer;
        if (is_array($buf) && count($buf) > 0) {
            foreach ($buf as $msg) {
                $this->writeLog($msg[0], $msg[1]);
            }
        }
        Frontend::$message_buffer = [];
    }

    public static function initialise(Map $config): void
    {
        if (true !== $config['enable']) {
            return;
        }
        Frontend::$logger = new Frontend($config->get('level'), $config->get('backend'), $config->get('options'));
        eval('class log extends \Hazaar\Logger\Frontend{};');
    }

    public static function destroy(): void
    {
        if (Frontend::$logger instanceof Frontend) {
            Frontend::$logger->close();
        }
    }

    public static function write(string $tag, string $message, int $level = E_NOTICE): void
    {
        if (Frontend::$logger instanceof Frontend) {
            Frontend::$logger->writeLog($tag, $message, $level);
        } else {
            Frontend::$message_buffer[] = [
                $tag,
                $message,
                $level,
            ];
        }
    }

    /**
     * Log an ERROR message.
     */
    public static function e(string $tag, string $message): void
    {
        Frontend::write($tag, $message, LOG_ERR);
    }

    /**
     * Log a WARNING message.
     */
    public static function w(string $tag, string $message): void
    {
        Frontend::write($tag, $message, LOG_WARNING);
    }

    /**
     * Log a NOTICE message.
     */
    public static function n(string $tag, string $message): void
    {
        Frontend::write($tag, $message, LOG_NOTICE);
    }

    /**
     * Log a INFO message.
     */
    public static function i(string $tag, string $message): void
    {
        Frontend::write($tag, $message, LOG_INFO);
    }

    /**
     * Log a DEBUG message.
     */
    public static function d(string $tag, string $message): void
    {
        Frontend::write($tag, $message, LOG_DEBUG);
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
    public function writeLog(string $tag, array|object|string $message, int $level = E_NOTICE): void
    {
        if (!($level <= $this->level)) {
            return;
        }
        if (!$this->backend->can('write_objects')) {
            if (is_array($message) || is_object($message)) {
                $message = 'OBJECT DUMP:'.LINE_BREAK.preg_replace('/\n/', LINE_BREAK, print_r($message, true));
            }
        }
        $this->backend->write($tag, $message, $level);
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
