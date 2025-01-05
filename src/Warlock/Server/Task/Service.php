<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Task;

use Hazaar\Warlock\Server\Master;
use Hazaar\Warlock\Server\Task;

class Service extends Task
{
    public string $type = 'service';
    public string $name = 'Unnamed Service';

    public bool $enabled = false;
    public bool $dynamic = false;
    public bool $detach = false;

    /**
     * @var array<mixed>
     */
    public array $info;
    public int $delay = 0;
    public int $loglevel = W_WARN;

    public function construct(array &$data): void
    {
        $data['tag'] = $data['name'];
        parent::construct($data);
    }

    public function run(): void
    {
        if (false === $this->enabled) {
            $this->log->write(W_DEBUG, 'Service is disabled', $this->id);

            return;
        }
        if (!($root = getenv('APPLICATION_ROOT'))) {
            $root = '/';
        }
        $payload = [
            'applicationName' => Master::$config['sys']['applicationName'],
            'timezone' => date_default_timezone_get(),
            'config' => ['app' => ['root' => $root]],
            'name' => $this->name,
        ];
        // if ($config = $process->config) {
        //     $payload['config'] = array_merge($payload['config'], $config->toArray());
        // }
        $packet = Master::$protocol->encode('service', $payload);
        if ($this->write($packet)) {
            $this->log->write(W_DEBUG, 'Service started', $this->id);
            $this->status = TASK_RUNNING;
        } else {
            $this->log->write(W_DEBUG, 'Service failed to start', $this->id);
            $this->status = TASK_ERROR;
        }
    }

    public function disable(?int $expire = null): bool
    {
        $this['enabled'] = false;

        // if ($this['task'] instanceof Task\Service) {
        //     $this['task']->cancel($expire);
        // }

        return true;
    }
}
