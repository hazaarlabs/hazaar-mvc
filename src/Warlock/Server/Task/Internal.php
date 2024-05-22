<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Task;

use Hazaar\Cron;
use Hazaar\Warlock\Server\Task;

class Internal extends Task
{
    public string $type = 'internal';

    protected Cron $when;
    protected mixed $exec;

    public function construct(array &$data): void
    {
        if (!isset($data['when'])) {
            throw new \Exception('Internal tasks must have a when parameter');
        }
        $data['when'] = $when = new Cron($data['when']);
        $data['start'] = $when->getNextOccurrence();
        parent::construct($data);
    }

    public function touch(): ?int
    {
        return $this->start = $this->when->getNextOccurrence();
    }

    public function start(): void
    {
        try {
            $this->status = TASK_RUNNING;
            $taskName = $this->exec->callable[0]::class.'::'.$this->exec->callable[1];
            $this->log->write(W_DEBUG, 'INTERNAL: '.$taskName, $this->id);
            call_user_func_array($this->exec->callable, (array) ake($this->exec, 'params'));
            $this->status = TASK_COMPLETE;
        } catch (\Throwable $e) {
            $this->log->write(W_ERR, 'INTERNAL TASK ERROR: '.$e->getMessage(), $this->id);
            $this->status = TASK_ERROR;
        }
    }
}
