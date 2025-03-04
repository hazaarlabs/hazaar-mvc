<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

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

abstract class Task extends Process implements \Hazaar\Warlock\Interface\Task
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
    public int $respawn_delay = 0;

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
            $this->log->write(W_ERR, 'Failed to start task: '.$e->getMessage(), $this->name);
            $this->status = TASK_ERROR;
        }
    }

    public function run(): void
    {
        $this->log->write(W_ERR, 'Task run method not implemented for: '.get_class($this), $this->name);
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
            $this->log->write(W_WARN, "Client {$this->id} is not subscribed to event {$eventID}", $this->name);

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
            $this->log->write(W_DECODE, 'TASK<-PACKET: '.trim($packet, "\n"), $this->name);
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
        $this->log->write(W_DECODE, "TASK->PACKET: {$packet}", $this->name);

        return $this->write($packet);
    }

    public function commandUnsubscribe(string $eventID): bool
    {
        $this->log->write(W_DEBUG, "TASK<-UNSUBSCRIBE: EVENT={$eventID} ID={$this->id}", $this->name);
        if (($index = array_search($eventID, $this->subscriptions)) !== false) {
            unset($this->subscriptions[$index]);
        }

        return Master::$instance->unsubscribe($this->client, $eventID);
    }

    public function commandTrigger(string $eventID, mixed $data, bool $echoClient = true): bool
    {
        $this->log->write(W_DEBUG, "TASK<-TRIGGER: NAME={$eventID} ID={$this->id} ECHO=".strbool($echoClient), $this->name);

        return Master::$instance->trigger($eventID, $data, false === $echoClient ? $this->id : null);
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
            $this->log->write(W_DEBUG, 'STATUS: '.strtoupper($this->status()), $this->id);
            if (TASK_RETRY === $value) {
                ++$this->retries;
                $this->log->write(W_DEBUG, 'RETRIES: '.$this->retries, $this->id);
                $this->start = time() + Master::$config['task']['retry'];
            }
        });
        $this->defineEventHook('written', 'start', function ($value) {
            $this->log->write(W_DEBUG, 'START: '.date('Y-m-d H:i:s', $this->start), $this->id);
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
        $this->log->write(W_DEBUG, "TASK<-COMMAND: {$command} ID={$this->id}", $this->name);

        switch ($command) {
            case 'NOOP':
                $this->log->write(W_INFO, 'NOOP: '.print_r($payload, true), $this->name);

                return true;

            case 'OK':
                if ($payload) {
                    $this->log->write(W_INFO, $payload, $this->name);
                }

                return true;

            case 'ERROR':
                $this->log->write(W_ERR, $payload, $this->name);

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
                $this->log->write(W_DEBUG, $payload->data ?? null, $this->name);

                return true;

            default:
                return Master::$instance->processCommand($this->client, $command, $payload);
        }
    }

    /**
     * @param array<string,mixed> $filter
     */
    private function commandSubscribe(string $eventID, ?array $filter = null): bool
    {
        $this->log->write(W_DEBUG, "TASK<-SUBSCRIBE: EVENT={$eventID} ID={$this->id}", $this->name);
        $this->subscriptions[] = $eventID;

        return Master::$instance->subscribe($this->client, $eventID, $filter);
    }

    private function commandLog(\stdClass $payload): bool
    {
        if (!property_exists($payload, 'msg')) {
            throw new \Exception('Unable to write to log without a log message!');
        }
        $level = $payload->level ?? W_INFO;
        $name = $payload->name ?? $this->name;
        if (is_array($payload->msg)) {
            foreach ($payload->msg as $msg) {
                $this->commandLog((object) ['level' => $level, 'msg' => $msg, 'name' => $name]);
            }
        } else {
            $this->log->write($level, $payload->msg ?? '--', $name);
        }

        return true;
    }
}
