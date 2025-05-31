<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Agent\Task;

use Hazaar\Util\Cron;
use Hazaar\Warlock\Agent\Task;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\TaskStatus;

class Internal extends Task
{
    protected Cron $when;

    public function schedule(string $when): void
    {
        $this->when = new Cron($when);
        $this->start = $this->when->getNextOccurrence();
    }

    public function touch(): ?int
    {
        if (TaskStatus::RUNNING === $this->status) {
            return $this->start;
        }
        $this->status = TaskStatus::QUEUED;

        return $this->start = $this->when->getNextOccurrence();
    }

    public function start(): void
    {
        try {
            $this->status = TaskStatus::RUNNING;
            $taskName = $this->endpoint->getName();
            $this->log->write('INTERNAL: '.$taskName, LogLevel::DEBUG);
            $this->endpoint->run();
            $this->status = TaskStatus::COMPLETE;
            $this->touch();
        } catch (\Throwable $e) {
            $this->log->write('INTERNAL TASK ERROR: '.$e->getMessage(), LogLevel::ERROR);
            $this->status = TaskStatus::ERROR;
        }
    }
}
