<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent\Task;

use Hazaar\Util\Cron;
use Hazaar\Warlock\Agent\Task;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\PacketType;
use Hazaar\Warlock\Enum\TaskStatus;

class Runner extends Task
{
    public ?Cron $when = null;
    public int $timeout = 60;

    public function schedule(int|string $when): self
    {
        if (is_int($when)) {
            $this->start = $when;
        } else {
            $this->when = new Cron($when);
            $this->start = $this->when->getNextOccurrence();
        }

        return $this;
    }

    public function touch(): ?int
    {
        if ($this->when) {
            $this->start = $this->when->getNextOccurrence();
        }

        return $this->start;
    }

    public function start(): void
    {
        $now = time();
        $this->log->write('Starting task execution', LogLevel::NOTICE);
        $this->log->write('NOW:  '.date('c', $now), LogLevel::DEBUG);
        $this->log->write('WHEN: '.date('c', $this->start), LogLevel::DEBUG);
        $late = $now - $this->start;
        if ($late > 0) {
            $this->log->write("LATE: {$late} seconds", LogLevel::DEBUG);
        }
        if ($this->retries > 0) {
            $this->log->write('RETRY: '.$this->retries, LogLevel::DEBUG);
        }
        parent::start();
        $this->status = TaskStatus::RUNNING;
    }

    public function run(): self
    {
        $payload = [
            'endpoint' => $this->endpoint->toArray(),
        ];
        if (count($this->params) > 0) {
            $payload['params'] = $this->params;
        }
        $result = $this->send(PacketType::EXEC, $payload);
        if (false === $result) {
            $this->log->write('Failed to send execution command', LogLevel::ERROR);
            $this->status = TaskStatus::ERROR;

            return $this;
        }
        $this->log->write('Execution command sent successfully', LogLevel::DEBUG);
        $this->status = TaskStatus::RUNNING;

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
