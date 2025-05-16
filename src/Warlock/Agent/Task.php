<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent;

use Hazaar\Util\Boolean;
use Hazaar\Warlock\Server\Component\Logger;
use Hazaar\Warlock\Server\Enum\LogLevel;

// STATUS CONSTANTS
define('TASK_INIT', 0);
define('TASK_QUEUED', 1);
define('TASK_RESTART', 2);
define('TASK_STARTING', 3);
define('TASK_RUNNING', 4);
define('TASK_COMPLETE', 5);
define('TASK_CANCELLED', 6);
define('TASK_ERROR', 7);
define('TASK_RETRY', 8);
define('TASK_WAIT', 9);

abstract class Task extends Process
{
    public ?Client $client = null;
    public string $type = 'task';
    public string $name;
    public int $status = TASK_INIT;
    public int $start = 0;

    public int $retries = 0;
    public int $expire = 0;
    public int $timeout = 0;
    public bool $respawn = false;
    public int $respawnDelay = 0;

    public string $accessKey = '';
    public int $lastHeartbeat = 0;
    public int $heartbeats = 0;
    public int $restarts = 0;

    /**
     * @var array<string>
     */
    private array $subscriptions = [];
    private string $__buffer;

    /**
     * @var array<string>
     */
    private static array $taskIDs = [];

    public function ready(): bool
    {
        return in_array($this->status, [TASK_QUEUED, TASK_RESTART, TASK_RETRY])
            && $this->start && time() >= $this->start;
    }

    public function start(): void
    {
        $this->status = TASK_STARTING;

        try {
            parent::start();
        } catch (\Exception $e) {
            $this->log->write('Failed to start task: '.$e->getMessage(), LogLevel::ERROR);
            $this->status = TASK_ERROR;
        }
    }

    public function run(): void
    {
        $this->log->write('Task run method not implemented for: '.get_class($this), LogLevel::ERROR);
        $this->status = TASK_ERROR;
    }

    public function cancel(int $expire = 30): void
    {
        if (null !== $this->client) {
            $this->status = TASK_CANCELLED;
            $this->expire = time() + $expire;
            $this->client->send('cancel');
        } else {
            $this->status = TASK_COMPLETE;
        }
    }

    public function status(): string
    {
        return match ($this->status) {
            TASK_INIT => 'init',
            TASK_QUEUED => 'queued',
            TASK_RESTART => 'restart',
            TASK_STARTING => 'starting',
            TASK_RUNNING => 'running',
            TASK_COMPLETE => 'complete',
            TASK_CANCELLED => 'cancelled',
            TASK_ERROR => 'error',
            TASK_RETRY => 'retrying',
            TASK_WAIT => 'wait',
            default => 'invalid',
        };
    }

    public function expired(): bool
    {
        return (TASK_WAIT === $this->status || TASK_CANCELLED === $this->status || TASK_ERROR === $this->status)
            && $this->expire > 0 && time() >= $this->expire;
    }

    public function sendEvent(string $eventID, string $triggerID, mixed $data): bool|int
    {
        if (!in_array($eventID, $this->subscriptions)) {
            $this->log->write("Client {$this->id} is not subscribed to event {$eventID}", LogLevel::WARN);

            return false;
        }
        $packet = [
            'id' => $eventID,
            'trigger' => $triggerID,
            'time' => microtime(true),
            'data' => $data,
        ];

        return $this->send('EVENT', $packet);
    }

    public function recv(string &$buf): void
    {
        $this->__buffer .= $buf;
        while ($packet = $this->processPacket($this->__buffer)) {
            $this->log->write('TASK<-PACKET: '.trim($packet, "\n"), LogLevel::DECODE);
            $payload = null;
            $time = null;
            if ($type = Master::$protocol->decode($packet, $payload, $time)) {
                if (!$this->processCommand($type, $payload)) {
                    throw new \Exception('Negative response returned while processing command: '.$type);
                }
            }
        }
    }

    public function send(string $command, mixed $payload = null): bool
    {
        if (!$this->process) {
            return false;
        }
        $packet = Master::$protocol->encode($command, $payload); // Override the timestamp.
        $this->log->write("TASK->PACKET: {$packet}", LogLevel::DECODE);

        return $this->write($packet);
    }

    public function commandUnsubscribe(string $eventID): bool
    {
        $this->log->write("TASK<-UNSUBSCRIBE: EVENT={$eventID} ID={$this->id}", LogLevel::DEBUG);
        if (($index = array_search($eventID, $this->subscriptions)) !== false) {
            unset($this->subscriptions[$index]);
        }

        return Main::$instance->unsubscribe($this->client, $eventID);
    }

    public function commandTrigger(string $eventID, mixed $data, bool $echoClient = true): bool
    {
        $this->log->write("TASK<-TRIGGER: NAME={$eventID} ID={$this->id} ECHO=".Boolean::toString($echoClient), LogLevel::DEBUG);

        return Main::$instance->trigger($eventID, $data, false === $echoClient ? $this->id : null);
    }

    final public function destruct(): void
    {
        if (($index = array_search($this->id, self::$taskIDs)) !== false) {
            unset(self::$taskIDs[$index]);
        }
    }

    public function touch(): ?int
    {
        return $this->start;
    }

    public function timeout(): bool
    {
        return $this->timeout > 0 && time() >= (int) ($this->start + $this->timeout);
    }

    protected function construct(array &$data): void
    {
        parent::construct($data);
        $this->start = time();
        $this->accessKey = uniqid();
        $this->log = new Logger();
        $this->defineEventHook('written', 'status', function ($value) {
            $this->log->write('STATUS: '.strtoupper($this->status()), LogLevel::DEBUG);
            if (TASK_RETRY === $value) {
                ++$this->retries;
                $this->log->write('RETRIES: '.$this->retries, LogLevel::DEBUG);
                $this->start = time() + Master::$config['task']['retry'];
            }
        });
        $this->defineEventHook('written', 'start', function ($value) {
            $this->log->write('START: '.date('Y-m-d H:i:s', $this->start), LogLevel::DEBUG);
        });
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

    private function processCommand(string $command, mixed $payload = null): bool
    {
        if (!$command) {
            return false;
        }
        $this->log->write("TASK<-COMMAND: {$command} ID={$this->id}", LogLevel::DEBUG);

        switch ($command) {
            case 'NOOP':
                $this->log->write('NOOP: '.print_r($payload, true), LogLevel::INFO);

                return true;

            case 'OK':
                if ($payload) {
                    $this->log->write($payload, LogLevel::INFO);
                }

                return true;

            case 'ERROR':
                $this->log->write($payload, LogLevel::ERROR);

                return true;

            case 'SUBSCRIBE' :
                $filter = (property_exists($payload, 'filter') ? $payload->filter : null);

                return $this->commandSubscribe($payload->id, $filter);

            case 'UNSUBSCRIBE' :
                return $this->commandUnsubscribe($payload->id);

            case 'TRIGGER' :
                return $this->commandTrigger($payload->id, $payload->data ?? null, $payload['echo'] ?? false);

            case 'LOG':
                return $this->commandLog($payload);

            case 'DEBUG':
                $this->log->write($payload->data ?? null, LogLevel::DEBUG);

                return true;

            default:
                Main::$instance->processCommand($this->client, $command, $payload);

                return true;
        }
    }

    /**
     * @param array<string,mixed> $filter
     */
    private function commandSubscribe(string $eventID, ?array $filter = null): bool
    {
        $this->log->write("TASK<-SUBSCRIBE: EVENT={$eventID} ID={$this->id}", LogLevel::DEBUG);
        $this->subscriptions[] = $eventID;

        return Main::$instance->subscribe($this->client, $eventID, $filter);
    }

    private function commandLog(\stdClass $payload): bool
    {
        if (!property_exists($payload, 'msg')) {
            throw new \Exception('Unable to write to log without a log message!');
        }
        $level = $payload->level ?? LogLevel::INFO;
        if (is_array($payload->msg)) {
            foreach ($payload->msg as $msg) {
                $this->commandLog((object) ['level' => $level, 'msg' => $msg]);
            }
        } else {
            $this->log->write($payload->msg ?? '--', $level);
        }

        return true;
    }
}
