<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Logger;

class Watcher
{
    private Logger $log;

    /**
     * @var array<string,resource>
     */
    private array $processes = [];

    /**
     * @var array<string,bool|int|string>
     */
    private array $config;

    /**
     * @param array<string,bool|int|string> $config
     */
    public function __construct(Logger $log, array $config)
    {
        $this->log = $log;
        $this->config = $config;
        $this->log->setPrefix('watcher');
    }

    public function process(): void
    {
        if (!$this->config['enabled']) {
            return;
        }
        $this->log->write('PROCESS COUNT='.count($this->processes), LogLevel::DEBUG);
        // Process the tasks
        foreach ($this->processes as $index => $process) {
            if (!is_resource($process)) {
                continue;
            }
            $status = proc_get_status($process);
            if ($status['running']) {
                $this->log->write($index.' running: '.$status['pid'], LogLevel::DEBUG);

                continue;
            }
            $this->log->write('Process '.$status['pid'].' exited with code '.$status['exitcode'], LogLevel::DEBUG);
            proc_close($process);
            unset($this->processes[$index]);
        }
    }

    public function start(): bool
    {
        $cmd = 'php '.escapeshellarg(__DIR__.'/Agent/Main.php');
        $this->processes['agent'] = proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        return true;
    }

    public function stop(): bool
    {
        foreach ($this->processes as $process) {
            if (!is_resource($process)) {
                continue;
            }
            proc_terminate($process);
        }
        $this->processes = [];

        return true;
    }
}
