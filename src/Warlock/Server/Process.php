<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Model;
use Hazaar\Util\Version;
use Hazaar\Warlock\Server\Component\Logger;
use Hazaar\Warlock\Server\Enum\LogLevel;

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
                $this->log->write('Excess output content on closing process', LogLevel::WARN);
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
        $this->log->write('TERMINATE: PID='.$status['pid'], LogLevel::DEBUG);
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
            $procCmd = [
                $phpBinary,
                basename($cmd),
                '-d',
            ];

            if ($this->tag) {
                $procCmd[] = '--name';
                $procCmd[] = $this->tag;
            }
            $this->log->write('EXEC='.implode(' ', $procCmd), LogLevel::DEBUG);
        } else {
            $procCmd = $phpBinary.' "'.basename($cmd).'" -d'.($this->tag ? ' --name '.$this->tag : '');
            $this->log->write('EXEC='.$procCmd, LogLevel::DEBUG);
        }
        $this->log->write('CWD='.$cwd, LogLevel::DEBUG);
        $this->process = proc_open($procCmd, $descriptorspec, $pipes, $cwd, $env);
        if (!is_resource($this->process)) {
            throw new \Exception('Failed to start the process');
        }
        $this->pipes = $pipes;
        $this->procStatus = proc_get_status($this->process);
        $this->log->write('PID: '.$this->procStatus['pid'], LogLevel::NOTICE);
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    final protected function write(string $packet): bool
    {
        $len = strlen($packet .= "\n");
        $this->log->write("PROCESS->PIPE: BYTES={$len} ID={$this->id}", LogLevel::DEBUG);
        $this->log->write('PROCESS->PACKET: '.trim($packet), LogLevel::DECODE);
        $bytesSent = @fwrite($this->pipes[0], $packet, $len);
        if (false === $bytesSent) {
            $this->log->write('An error occured while sending to the client. Pipe has disappeared!?', LogLevel::WARN);

            return false;
        }
        if ($bytesSent !== $len) {
            $this->log->write($bytesSent.' bytes have been sent instead of the '.$len.' bytes expected', LogLevel::ERROR);

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
