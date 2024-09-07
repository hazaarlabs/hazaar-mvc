<?php

declare(strict_types=1);

namespace Hazaar\Logger\Backend;

use Hazaar\Application;
use Hazaar\Logger\Backend;

class File extends Backend
{
    /**
     * @var resource
     */
    private mixed $hLog = null;

    /**
     * @var resource
     */
    private mixed $hErr = null;

    private int $level_padding = 0;

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
        $this->level_padding = max(array_map('strlen', array_keys($this->levels))) - strlen(self::LOG_LEVEL_PREFIX);
    }

    public function postRun(): void
    {
        if (null !== $this->hLog) {
            fclose($this->hLog);
        }
        if (null !== $this->hErr) {
            fclose($this->hErr);
        }
    }

    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void
    {
        if (null !== $this->hLog) {
            return;
        }
        $remote = ake($_SERVER, 'REMOTE_ADDR', '--');
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
        $line[] = str_pad(strtoupper($this->getLogLevelName($level)), $this->level_padding, ' ', STR_PAD_RIGHT);
        if (null !== $tag) {
            $line[] = $tag;
        }
        $line[] = $message;
        fwrite($this->hLog, implode(' | ', $line)."\r\n");
        if (null !== $this->hErr && LOG_NOTICE == $level) {
            fwrite($this->hErr, implode(' | ', $line)."\r\n");
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
