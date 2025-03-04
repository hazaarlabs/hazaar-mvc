<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Model;
use Hazaar\Version;

if (!defined('SERVER_PATH')) {
    throw new \Exception('SERVER_PATH is not defined');
}

abstract class Process extends Model
{
    public Struct\Application $application;
    public string $id;
    public ?string $tag = null;

    /**
     * @var array<string,mixed>
     */
    public array $procStatus = [
        'pid' => -1,
        'running' => false,
    ];
    protected int $pid = -1;
    protected int $exitcode = -1;

    /**
     * @var array<resource>
     */
    protected array $pipes;
    protected Logger $log;

    /**
     * @var ?resource
     */
    protected mixed $process = null;

    /**
     * @var array<string>
     */
    private static array $processIDs = [];

    final public function isRunning(): bool
    {
        $procStatus = $this->get('procStatus');

        return $procStatus['running'] ?? false;
    }

    /**
     * @return resource
     */
    final public function getReadPipe(): mixed
    {
        return $this->pipes[1];
    }

    /**
     * @return resource
     */
    final public function getWritePipe(): mixed
    {
        return $this->pipes[0];
    }

    final public function readErrorPipe(): false|string
    {
        $read = [$this->pipes[2]];
        $write = null;
        $except = null;
        if (!(stream_select($read, $write, $except, 0, 0) > 0)) {
            return false;
        }
        $buffer = stream_get_contents($this->pipes[2]);
        if (0 === strlen($buffer)) {
            return false;
        }

        return $buffer;
    }

    /**
     * Closes the process and returns the exit code.
     *
     * This method closes all the pipes associated with the process and returns the exit code of the process.
     * If there is any excess output content on closing the process, a warning message is logged and the content is echoed.
     *
     * @return int the exit code of the process
     */
    final public function close(): int
    {
        // Make sure we close all the pipes
        foreach ($this->pipes as $sid => $pipe) {
            if (0 === $sid) {
                continue;
            }
            if ($input = stream_get_contents($pipe)) {
                $this->log->write(W_WARN, 'Excess output content on closing process', $this->tag);
                echo str_repeat('-', 30)."\n".$input."\n".str_repeat('-', 30)."\n";
            }
            fclose($pipe);
        }

        return proc_close($this->process);
    }

    /**
     * Terminate the process and all its child processes.
     *
     * @return bool returns true if the process was successfully terminated, false otherwise
     */
    final public function terminate(): bool
    {
        if (!is_resource($this->process)) {
            return false;
        }
        // posix_kill($status['pid'], 15);

        // $pids = preg_split('/\s+/', shell_exec("ps -o pid --no-heading --ppid {$this->pid}"));
        // foreach ($pids as $pid) {
        //     if (!is_numeric($pid)) {
        //         continue;
        //     }
        //     $this->log->write(W_DEBUG, 'TERMINATE: PID='.$pid, $this->tag);
        //     posix_kill((int) $pid, 15);
        // }
        $status = proc_get_status($this->process);
        $this->log->write(W_DEBUG, 'TERMINATE: PID='.$status['pid'], $this->id);
        if (false === proc_terminate($this->process, 15)) {
            return false;
        }
        $this->process = null;

        return true;
    }

    /**
     * Starts the server process.
     *
     * @throws \Exception throws an exception if the application command runner or PHP CLI binary is not found or not executable
     */
    public function start(): void
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $config = Master::$config;
        $env = array_filter(array_merge($_SERVER, [
            'APPLICATION_PATH' => $this->application['path'],
            'APPLICATION_ENV' => $this->application['env'],
            'HAZAAR_SID' => $config['sys']['id'],
            'HAZAAR_ADMIN_KEY' => $config['admin']['key'],
            'USERNAME' => (array_key_exists('USERNAME', $_SERVER) ? $_SERVER['USERNAME'] : null),
        ]), 'is_string');
        $cmd = realpath(SERVER_PATH.'/Runner.php');
        if (!$cmd || !file_exists($cmd)) {
            throw new \Exception('Application command runner could not be found!');
        }
        $phpBinary = Master::$config['sys']['phpBinary'];
        if (!file_exists($phpBinary)) {
            throw new \Exception('The PHP CLI binary does not exist at '.$phpBinary);
        }
        if (!is_executable($phpBinary)) {
            throw new \Exception('The PHP CLI binary exists but is not executable!');
        }
        $php = new Version(phpversion());
        $cwd = dirname($cmd);
        if (1 === $php->compareTo('7.4')) {
            $proc_cmd = [
                $phpBinary,
                basename($cmd),
                '-d',
            ];

            if ($this->tag) {
                $proc_cmd[] = '--name';
                $proc_cmd[] = $this->tag;
            }
            $this->log->write(W_DEBUG, 'EXEC='.implode(' ', $proc_cmd), $this->id);
        } else {
            $proc_cmd = $phpBinary.' "'.basename($cmd).'" -d'.($this->tag ? ' --name '.$this->tag : '');
            $this->log->write(W_DEBUG, 'EXEC='.$proc_cmd, $this->id);
        }
        $this->log->write(W_DEBUG, 'CWD='.$cwd, $this->id);
        $this->process = proc_open($proc_cmd, $descriptorspec, $pipes, $cwd, $env);
        if (!is_resource($this->process)) {
            throw new \Exception('Failed to start the process');
        }
        $this->pipes = $pipes;
        $this->procStatus = proc_get_status($this->process);
        $this->log->write(W_NOTICE, 'PID: '.$this->procStatus['pid'], $this->id);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    final protected function write(string $packet): bool
    {
        $len = strlen($packet .= "\n");
        $this->log->write(W_DEBUG, "PROCESS->PIPE: BYTES={$len} ID={$this->id}", $this->tag);
        $this->log->write(W_DECODE, 'PROCESS->PACKET: '.trim($packet), $this->tag);
        $bytes_sent = @fwrite($this->pipes[0], $packet, $len);
        if (false === $bytes_sent) {
            $this->log->write(W_WARN, 'An error occured while sending to the client. Pipe has disappeared!?', $this->tag);

            return false;
        }
        if ($bytes_sent !== $len) {
            $this->log->write(W_ERR, $bytes_sent.' bytes have been sent instead of the '.$len.' bytes expected', $this->tag);

            return false;
        }

        return true;
    }

    final protected function getProcessID(): string
    {
        $count = 0;
        $pid = null;
        while (in_array($pid = uniqid(), self::$processIDs)) {
            ++$count;
            if ($count >= 10) {
                throw new \Exception("Unable to generate task ID after {$count} attempts . Giving up . This is bad! ");
            }
        }
        self::$processIDs[] = $pid;

        return $pid;
    }

    protected function constructed(): void
    {
        $this->id = $this->getProcessID();
        $this->defineEventHook('read', 'pid', function () {
            return $this->get('procStatus')['pid'] ?? null;
        });
        $this->defineEventHook('read', 'exitcode', function () {
            return $this->get('procStatus')['exitcode'] ?? null;
        });
        $this->defineEventHook('read', 'procStatus', function () {
            if (!is_resource($this->process)) {
                return false;
            }

            return proc_get_status($this->process);
        });
        $this->log = new Logger();
    }
}
