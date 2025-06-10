<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent\Task;

use Hazaar\Warlock\Agent\Main;
use Hazaar\Warlock\Agent\Task;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\TaskStatus;
use Hazaar\Warlock\Logger;

class Service extends Task
{
    /**
     * @var array<mixed> service configuration
     */
    public array $childConfig = [];
    public string $name = 'Unnamed Service';
    public bool $enabled = true;

    /**
     * @var array<mixed>
     */
    public array $info;
    public int $delay = 0;
    public LogLevel $loglevel = LogLevel::WARN;

    /**
     * @param array<mixed> $config
     */
    public function __construct(Main $agent, Logger $log, array $config = [])
    {
        parent::__construct($agent, $log);
        $this->status = TaskStatus::INIT;
        $this->childConfig = $config;
        $this->start = $config['delay'] ?? 1; // Default start time is immediately
        if (!isset($this->config['timezone'])) {
            $this->config['timezone'] = date_default_timezone_get();
        }
    }

    public function run(): self
    {
        if (false === $this->enabled) {
            $this->log->write('Service is disabled', LogLevel::DEBUG);

            return $this;
        }
        $payload = [
            'name' => $this->name,
            'application' => $this->application,
            'config' => $this->childConfig,
            'endpoint' => $this->endpoint,
        ];
        if ($this->send(PacketType::SERVICE, $payload)) {
            $this->log->write('Service started', LogLevel::DEBUG);
            $this->status = TaskStatus::RUNNING;
        } else {
            $this->log->write('Service failed to start', LogLevel::DEBUG);
            $this->status = TaskStatus::ERROR;
        }

        return $this;
    }

    protected function processCommand(PacketType $command, mixed $payload = null): bool
    {
        if (PacketType::EXEC === $command) {
            $this->log->write('Processing EXEC command', LogLevel::DEBUG);
            $this->run();

            return true;
        }

        return parent::processCommand($command, $payload);
    }
}
