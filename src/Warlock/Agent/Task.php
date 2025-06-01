<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent;

use Hazaar\Util\Boolean;
use Hazaar\Warlock\Agent\Struct\Endpoint;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\TaskStatus;
use Hazaar\Warlock\Logger;

abstract class Task extends Process
{
    public TaskStatus $status = TaskStatus::INIT;
    public Main $agent;
    public int $start = 0;
    public int $retries = 0;
    public int $expire = 0;
    public int $timeout = 0;
    public bool $respawn = false;
    public int $respawnDelay = 0;
    public int $lastHeartbeat = 0;
    public int $heartbeats = 0;

    /**
     * @var array<string,mixed>
     *
     * @detail      Configuration for the task, such as retry count, retry delay, and expiration time.
     */
    public array $config = [];

    protected Endpoint $endpoint;

    /**
     * @var array<mixed>
     */
    protected array $params = [];

    private string $recvBuffer = '';

    /**
     * @var array<string>
     */
    private array $subscriptions = [];

    public function __construct(Main $agent, Logger $log)
    {
        parent::__construct($log);
        $this->agent = $agent;
        $this->log->write('Task created with ID: '.$this->id, LogLevel::DEBUG);
    }

    /**
     * Executes the task with the given callable and parameters.
     */
    public function exec(Endpoint $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function ready(): bool
    {
        return in_array($this->status, [TaskStatus::QUEUED, TaskStatus::RESTART, TaskStatus::RETRY])
            && $this->start && time() >= $this->start;
    }

    public function start(): void
    {
        $this->status = TaskStatus::STARTING;

        try {
            parent::start();
        } catch (\Exception $e) {
            $this->log->write('Failed to start task: '.$e->getMessage(), LogLevel::ERROR);
            $this->status = TaskStatus::ERROR;
        }
    }

    public function process(): void
    {
        switch ($this->status) {
            case TaskStatus::QUEUED:
            case TaskStatus::RESTART:
            case TaskStatus::RETRY: // Tasks that are queued and ready to execute or ready to restart an execution retry.
                if ($this->ready()) {
                    $this->start();
                }

                break;

            case TaskStatus::RUNNING:
                $this->monitor();
                if ($this->timeout()) {
                    $this->log->write('Process taking too long to execute - Attempting to kill it.', LogLevel::WARN);
                    if ($this->terminate()) {
                        $this->log->write('Terminate signal sent.', LogLevel::DEBUG);
                    } else {
                        $this->log->write('Failed to send terminate signal.', LogLevel::ERROR);
                    }
                }

                break;

            case TaskStatus::CANCELLED:
                if ($this->expired()) {
                    $this->terminate();

                    break;
                }

                // no break
            case TaskStatus::COMPLETE:
                if (($next = $this->touch()) > time()) {
                    $this->status = TaskStatus::QUEUED;
                    $this->retries = 0;
                    $this->log->write('Next execution at: '.date('c', $next), LogLevel::NOTICE);
                } else {
                    $this->status = TaskStatus::WAIT;
                    $this->expire = time() + $this->config['expire'];
                }

                break;

            case TaskStatus::ERROR:
                if ($this->retries < $this->config['task']['retries']) {
                    $this->log->write('Task failed. Retrying in '.$this->config['task']['retry'].' seconds', LogLevel::NOTICE);
                    $this->status = TaskStatus::RETRY;
                } else {
                    $this->log->write('Task execution failed', LogLevel::ERROR);
                    $this->status = TaskStatus::WAIT;
                    $this->expire = time() + $this->config['task']['expire'];
                }

                break;
        }
    }

    public function run(): void
    {
        $this->log->write('Task run method not implemented for: '.get_class($this), LogLevel::ERROR);
        $this->status = TaskStatus::ERROR;
    }

    public function cancel(int $expire = 30): void
    {
        // if (null !== $this->client) {
        //     $this->status = TaskStatus::CANCELLED;
        //     $this->expire = time() + $expire;
        //     $this->client->send('cancel');
        // } else {
        //     $this->status = TaskStatus::COMPLETE;
        // }
        // Stop the task if it is currently running
        if (TaskStatus::RUNNING === $this->status && $this->isRunning()) {
            // $this->log->write('Stopping running '.$task->type, LogLevel::NOTICE);
            $this->terminate();
        }
        $this->status = TaskStatus::CANCELLED;
        // Expire the task in 30 seconds
        $this->expire = time() + $this->config['expire'];
    }

    public function expired(): bool
    {
        return (TaskStatus::WAIT === $this->status || TaskStatus::CANCELLED === $this->status || TaskStatus::ERROR === $this->status)
            && $this->expire > 0 && time() >= $this->expire;
    }

    // public function sendEvent(string $eventID, string $triggerID, mixed $data): bool|int
    // {
    //     if (!in_array($eventID, $this->subscriptions)) {
    //         $this->log->write("Client {$this->id} is not subscribed to event {$eventID}", LogLevel::WARN);

    //         return false;
    //     }
    //     $packet = [
    //         'id' => $eventID,
    //         'trigger' => $triggerID,
    //         'time' => microtime(true),
    //         'data' => $data,
    //     ];

    //     return $this->send(PacketType::EVENT, $packet);
    // }

    public function recv(string &$buf): void
    {
        $this->recvBuffer .= $buf;
        while ($packet = $this->processPacket($this->recvBuffer)) {
            $this->log->write('TASK<-PACKET: '.trim($packet, "\n"), LogLevel::DECODE);
            $payload = null;
            $time = null;
            if ($type = $this->agent->protocol->decode($packet, $payload, $time)) {
                if (!$this->processCommand($type, $payload)) {
                    throw new \Exception('Negative response returned while processing command: '.$type->name);
                }
            }
        }
    }

    // public function send(PacketType $command, mixed $payload = null): bool
    // {
    //     if (!$this->process) {
    //         return false;
    //     }
    //     $packet = Main::$protocol->encode($command, $payload); // Override the timestamp.
    //     $this->log->write("TASK->PACKET: {$packet}", LogLevel::DECODE);

    //     return $this->write($packet);
    // }

    // public function commandUnsubscribe(string $eventID): bool
    // {
    //     $this->log->write("TASK<-UNSUBSCRIBE: EVENT={$eventID} ID={$this->id}", LogLevel::DEBUG);
    //     if (($index = array_search($eventID, $this->subscriptions)) !== false) {
    //         unset($this->subscriptions[$index]);
    //     }

    //     return Main::$instance->unsubscribe($this->client, $eventID);
    // }

    // public function commandTrigger(string $eventID, mixed $data, bool $echoClient = true): bool
    // {
    //     $this->log->write("TASK<-TRIGGER: NAME={$eventID} ID={$this->id} ECHO=".Boolean::toString($echoClient), LogLevel::DEBUG);

    //     return Main::$instance->trigger($eventID, $data, false === $echoClient ? $this->id : null);
    // }

    final public function destruct(): void
    {
        // if (($index = array_search($this->id, self::$taskIDs)) !== false) {
        //     unset(self::$taskIDs[$index]);
        // }
    }

    public function touch(): ?int
    {
        return $this->start;
    }

    public function timeout(): bool
    {
        return $this->timeout > 0 && time() >= (int) ($this->start + $this->timeout);
    }

    public function send(PacketType $command, mixed $payload = null): bool
    {
        if (!$this->process) {
            return false;
        }
        $packet = $this->agent->protocol->encode($command, $payload); // Override the timestamp.
        $this->log->write("TASK->PACKET: {$packet}", LogLevel::DECODE);

        return $this->write($packet);
    }

    private function commandTrigger(string $eventID, mixed $data, bool $echoClient = true): bool
    {
        $this->log->write("TASK<-TRIGGER: NAME={$eventID} ID={$this->id} ECHO=".Boolean::toString($echoClient), LogLevel::DEBUG);

        return $this->agent->trigger($eventID, $data, $echoClient);
    }

    /**
     * @param array<string,mixed> $filter
     */
    private function commandSubscribe(string $eventID, ?array $filter = null): bool
    {
        $this->log->write("TASK<-SUBSCRIBE: EVENT={$eventID} ID={$this->id}", LogLevel::DEBUG);
        $this->subscriptions[] = $eventID;

        return true;
    }

    private function commandUnsubscribe(string $eventID): bool
    {
        $this->log->write("TASK<-UNSUBSCRIBE: EVENT={$eventID} ID={$this->id}", LogLevel::DEBUG);
        if (($index = array_search($eventID, $this->subscriptions)) !== false) {
            unset($this->subscriptions[$index]);
        }

        return true;
    }

    private function commandLog(\stdClass $payload): bool
    {
        if (!property_exists($payload, 'msg')) {
            throw new \Exception('Unable to write to log without a log message!');
        }
        $level = LogLevel::tryFrom($payload->level) ?? LogLevel::INFO;
        if (is_array($payload->msg)) {
            foreach ($payload->msg as $msg) {
                $this->commandLog((object) ['level' => $level, 'msg' => $msg]);
            }
        } else {
            $this->log->write($payload->msg ?? '--', $level);
        }

        return true;
    }

    private function monitor(): void
    {
        // $this->log->write('PROCESS->RUNNING: PID='.$this->pid.' ID='.$this->id, LogLevel::DEBUG);
        $status = proc_get_status($this->process);
        if (true === $status['running']) {
            try {
                $pipe = $this->getReadPipe();
                if ($buffer = stream_get_contents($pipe)) {
                    $this->recv($buffer);
                }
                // Receive any error from STDERR
                if (($output = $this->readErrorPipe()) !== false) {
                    $this->log->write("PROCESS ERROR:\n{$output}", LogLevel::ERROR);
                }
            } catch (\Throwable $e) {
                $this->log->write('EXCEPTION #'
                    .$e->getCode()
                    .' on line '.$e->getLine()
                    .' in file '.$e->getFile()
                    .': '.$e->getMessage(), LogLevel::ERROR);
            }

            return;
        }
        $this->log->write("PROCESS->STOP: PID={$status['pid']} ID=".$this->id, LogLevel::DEBUG);
        // One last check of the error buffer
        if (($output = $this->readErrorPipe()) !== false) {
            $this->log->write("PROCESS ERROR:\n{$output}", LogLevel::ERROR);
        }
        $pipe = $this->getReadPipe();
        if ($buffer = stream_get_contents($pipe)) {
            $this->recv($buffer);
        }
        $this->close();
        // Process a Service shutdown.
        $this->log->write('Process exited with return code: '.$status['exitcode'], LogLevel::NOTICE);
        if ($status['exitcode'] > 0) {
            $this->log->write('Execution completed with error.', LogLevel::WARN);
            $this->status = TaskStatus::ERROR;
        } else {
            $this->log->write('Execution completed successfully.', LogLevel::NOTICE);
            $this->status = TaskStatus::COMPLETE;
        }
    }

    private function processPacket(?string &$buffer = null): false|string
    {
        if (!$buffer || ($pos = strpos($buffer, "\n")) === false) {
            return false;
        }
        $packet = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 1);

        return $packet;
    }

    private function processCommand(PacketType $command, mixed $payload = null): bool
    {
        $this->log->write("TASK<-COMMAND: {$command->name} ID={$this->id}", LogLevel::DEBUG);

        switch ($command) {
            case PacketType::NOOP:
                $this->log->write('NOOP: '.print_r($payload, true), LogLevel::INFO);

                return true;

            case PacketType::OK:
                if ($payload) {
                    $this->log->write($payload, LogLevel::INFO);
                }

                return true;

            case PacketType::ERROR:
                $this->log->write($payload, LogLevel::ERROR);

                return true;

            case PacketType::SUBSCRIBE:
                $filter = (property_exists($payload, 'filter') ? $payload->filter : null);

                return $this->commandSubscribe($payload->id, $filter);

            case PacketType::UNSUBSCRIBE:
                return $this->commandUnsubscribe($payload->id);

            case PacketType::TRIGGER:
                return $this->commandTrigger($payload->id, $payload->data ?? null, $payload->echo ?? false);

            case PacketType::LOG:
                return $this->commandLog($payload);

            case PacketType::DEBUG:
                $this->log->write($payload->data ?? null, LogLevel::DEBUG);

                return true;
                // default:
                // return $this->agent->processCommand($this, $command, $payload);
        }

        return false;
    }
}
