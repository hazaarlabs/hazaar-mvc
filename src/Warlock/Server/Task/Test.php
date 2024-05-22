<?php

namespace Hazaar\Warlock\Server\Task;

use Hazaar\Warlock\Server\Task;

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
        $this->log->write(W_DEBUG, 'STARTING TEST TASK', $this->id);
        $this->status = TASK_STARTING;
    }

    public function run(): void
    {
        $this->log->write(W_DEBUG, 'RUNNING TEST TASK', $this->id);
        $this->status = TASK_RUNNING;
        $this->started = time();
    }

    protected function resolveStatus(mixed $value): mixed
    {
        $this->log->write(W_DEBUG, 'STATUS: '.$value, $this->id);

        switch ($value) {
            case TASK_INIT:
            case TASK_RUNNING:
                if (time() - $this->started > 5) {
                    if ($this->retries < 3) {
                        $this->log->write(W_DEBUG, 'ERRORED TEST TASK', $this->id);

                        return TASK_ERROR;
                    }
                    $this->log->write(W_DEBUG, 'COMPLETED TEST TASK', $this->id);

                    return TASK_COMPLETE;
                }

                break;

            case TASK_COMPLETE:
                $this->log->write(W_DEBUG, 'Task is complete!', $this->id);

                break;
        }
        if (TASK_RUNNING === $this->status && time() - $this->started > 5) {
            return TASK_COMPLETE;
        }

        return $value;
    }
}
