<?php

namespace Hazaar\Warlock\Server\Task;

use Hazaar\Warlock\Agent\Task;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\TaskStatus;

/**
 * @internal
 */
class Test extends Task
{
    public string $type = 'test';
    private int $started = 0;

    public function construct(array &$data): void
    {
        parent::construct($data);
        $this->start = time() + 5;
        $this->defineEventHook('read', 'status', [$this, 'resolveStatus']);
    }

    public function start(): void
    {
        $this->log->write('STARTING TEST TASK', LogLevel::DEBUG);
        $this->status = TaskStatus::STARTING;
    }

    public function run(): void
    {
        $this->log->write('RUNNING TEST TASK', LogLevel::DEBUG);
        $this->status = TaskStatus::RUNNING;
        $this->started = time();
    }

    protected function resolveStatus(mixed $value): mixed
    {
        $this->log->write('STATUS: '.$value, LogLevel::DEBUG);

        switch ($value) {
            case TaskStatus::INIT:
            case TaskStatus::RUNNING:
                if (time() - $this->started > 5) {
                    if ($this->retries < 3) {
                        $this->log->write('ERRORED TEST TASK', LogLevel::DEBUG);

                        return TaskStatus::ERROR;
                    }
                    $this->log->write('COMPLETED TEST TASK', LogLevel::DEBUG);

                    return TaskStatus::COMPLETE;
                }

                break;

            case TaskStatus::COMPLETE:
                $this->log->write('Task is complete!', LogLevel::DEBUG);

                break;
        }
        if (TaskStatus::RUNNING === $this->status && time() - $this->started > 5) {
            return TaskStatus::COMPLETE;
        }

        return $value;
    }
}
