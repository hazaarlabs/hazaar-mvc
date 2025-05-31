<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server\Task;

use Hazaar\Warlock\Agent\Task;
use Hazaar\Warlock\Enum\LogLevel;
use Hazaar\Warlock\Enum\TaskStatus;

class Service extends Task
{
    public string $name = 'Unnamed Service';
    public bool $enabled = true;

    /**
     * @var array<mixed>
     */
    public array $info;
    public int $delay = 0;
    public LogLevel $loglevel = LogLevel::WARN;

    public function construct(array &$data): void
    {
        $data['tag'] = $data['name'];
        parent::construct($data);
    }

    public function run(): void
    {
        if (false === $this->enabled) {
            $this->log->write('Service is disabled', LogLevel::DEBUG);

            return;
        }
        if (!($root = getenv('APPLICATION_ROOT'))) {
            $root = '/';
        }
        $payload = [
            'timezone' => date_default_timezone_get(),
            'config' => ['app' => ['root' => $root]],
            'name' => $this->name,
        ];
        // if ($config = $process->config) {
        //     $payload['config'] = array_merge($payload['config'], $config->toArray());
        // }
        // $packet = Master::$protocol->encode('service', $payload);
        // if ($this->write($packet)) {
        //     $this->log->write('Service started', LogLevel::DEBUG);
        //     $this->status = TaskStatus::RUNNING;
        // } else {
        //     $this->log->write('Service failed to start', LogLevel::DEBUG);
        //     $this->status = TaskStatus::ERROR;
        // }
    }
}
