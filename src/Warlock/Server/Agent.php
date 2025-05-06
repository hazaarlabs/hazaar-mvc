<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Server;

use Hazaar\Warlock\Config;

class Agent
{
    private array $processes = [];

    public function __construct(Config $config)
    {
        // Initialize the services array
        if ($config['runner']['enabled'] ?? false) {
            $this->startRunner($config->getSourceFile());
        }
    }

    public function process(): void
    {
        // Process the tasks
        // foreach ($this->processes as $process) {
        //     $process->process();
        // }
    }

    private function startRunner(?string $configFile = null): void
    {
        $cmd = 'php '.escapeshellarg(__DIR__.'/Service/Runner.php');
        if ($configFile) {
            $cmd .= ' --config '.escapeshellarg($configFile);
        }
        $this->processes['runner'] = proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ]);
    }
}
