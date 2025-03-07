<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Application;
use Hazaar\Logger\Backend;

class File extends Backend
{
    /**
     * @var false|resource
     */
    private mixed $hLog = false;

    /**
     * @var false|resource
     */
    private mixed $hErr = false;

    private int $levelPadding = 0;

    public function init(): void
    {
        $this->addCapability('write_trace');
        $this->setDefaultOption('write_ip', true);
        $this->setDefaultOption('write_timestamp', true);
        $this->setDefaultOption('write_pid', false);
        $this->setDefaultOption('logfile', Application::getInstance()->getRuntimePath('hazaar.log'));
        if (($log_file = $this->getOption('logfile'))
            && is_writable(dirname($log_file))
            && (!\file_exists($log_file) || \is_writable($log_file))) {
            if (($this->hLog = fopen($log_file, 'a')) == false) {
                throw new Exception\OpenLogFileFailed($log_file);
            }
        }

        $this->setDefaultOption('errfile', Application::getInstance()->getRuntimePath('error.log'));
        if (($error_file = $this->getOption('errfile'))
            && is_writable(dirname($error_file))
            && (!\file_exists($error_file) || \is_writeable($error_file))) {
            if (($this->hErr = fopen($error_file, 'a')) == false) {
                throw new Exception\OpenLogFileFailed($error_file);
            }
        }
        $this->levelPadding = max(array_map('strlen', array_keys($this->levels))) - strlen(self::LOG_LEVEL_PREFIX);
    }

    public function postRun(): void
    {
        if ($this->hLog) {
            fclose($this->hLog);
        }
        if ($this->hErr) {
            fclose($this->hErr);
        }
    }

    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void
    {
        if (!$this->hLog) {
            return;
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? '--';
        $line = [];
        if ($this->getOption('write_ip')) {
            $line[] = $remote;
        }
        if ($this->getOption('write_timestamp')) {
            $line[] = date('Y-m-d H:i:s');
        }
        if ($this->getOption('write_pid')) {
            $line[] = getmypid();
        }
        $line[] = str_pad(strtoupper($this->getLogLevelName($level)), $this->levelPadding, ' ', STR_PAD_RIGHT);
        if (null !== $tag) {
            $line[] = $tag;
        }
        $line[] = $message;
        fwrite($this->hLog, implode(' | ', $line).PHP_EOL);
        if ($this->hErr && LOG_NOTICE == $level) {
            fwrite($this->hErr, implode(' | ', $line).PHP_EOL);
        }
    }

    public function trace(): void
    {
        ob_start();
        debug_print_backtrace();
        $trace = ob_get_clean();
        fwrite($this->hLog, $trace);
    }
}
