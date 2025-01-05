<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Task;

use Hazaar\Cron;
use Hazaar\Warlock\Server\Master;
use Hazaar\Warlock\Server\Task;

class Runner extends Task
{
    public string $type = 'runner';

    public ?Cron $when = null;
    public int $timeout = 60;
    public mixed $exec;
    public bool $event = false;
    public string $info;

    /**
     * @var array<mixed>
     */
    public array $params = [];

    public function construct(array &$data): void
    {
        if (isset($data['when'])) {
            if (is_int($data['when'])) {
                $data['start'] = time() + $data['when'];
                $data['when'] = null;
            } else {
                $data['when'] = new Cron($data['when']);
            }
        }
        parent::construct($data);
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
        $this->log->write(W_NOTICE, 'Starting task execution', $this->id);
        $this->log->write(W_DEBUG, 'NOW:  '.date(Master::$config['sys']['dateFormat'], $now), $this->id);
        $this->log->write(W_DEBUG, 'WHEN: '.date(Master::$config['sys']['dateFormat'], $this->start), $this->id);
        $late = $now - $this->start;
        if ($late > 0) {
            $this->log->write(W_DEBUG, "LATE: {$late} seconds", $this->id);
            ++$this->stats['lateExecs'];
        }
        if ($this->retries > 0) {
            $this->log->write(W_DEBUG, 'RETRY: '.$this->retries, $this->id);
        }

        parent::start();
    }

    public function run(): void
    {
        $payload = [
            'exec' => $this->exec,
        ];
        if (count($this->params) > 0) {
            $payload['params'] = $this->params;
        }
        $packet = Master::$protocol->encode('exec', $payload);
        if ($this->write($packet)) {
            $this->log->write(W_DEBUG, 'Runner started', $this->id);
            $this->status = TASK_RUNNING;
        } else {
            $this->log->write(W_DEBUG, 'Runner failed to start', $this->id);
            $this->status = TASK_ERROR;
        }
    }
}
