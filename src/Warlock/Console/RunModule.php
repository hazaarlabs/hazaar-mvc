<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Console;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\Util\Boolean;
use Hazaar\Warlock\Server\Main;

class RunModule extends Module
{
    private Main $warlock;

    public function configure(): void
    {
        $this->addCommand('run', [$this, 'startServer'])
            ->setDescription('Run the Warlock server')
            ->addOption(long: 'env', short: 'e', description: 'The environment to run the server in')
            ->addOption(long: 'config', description: 'The configuration file to use')
            ->addOption(long: 'silent', short: 's', description: 'Run the server in single process mode')
            ->addOption(long: 'daemon', short: 'd', description: 'Run the server in daemon mode')
        ;
        $this->addCommand('stop', [$this, 'stopServer'])
            ->setDescription('Stop the Warlock server')
            ->addOption('force', short: 'f', description: 'Force stop the server')
            ->addOption('pid', short: 'p', description: 'The PID file to use')
        ;
        $this->addCommand('restart', [$this, 'restartServer'])
            ->setDescription('Restart the Warlock server')
            ->addOption('force', short: 'f', description: 'Force restart the server')
            ->addOption('pid', short: 'p', description: 'The PID file to use')
        ;
    }

    protected function prepare(Input $input, Output $output): void
    {
        $env = $input->getOption('env') ?? 'development';
        $configFile = $input->getOption('config') ?? getcwd().'/warlock.json';
        $this->warlock = new Main(configFile: $configFile, env: $env);
    }

    protected function startServer(Input $input, Output $output): int
    {
        if (true === Boolean::from($input->getOption('silent') ?? false)) {
            $this->warlock->setSilent(true);
        }
        if (true === Boolean::from($input->getOption('daemon') ?? false)) {
            if (!function_exists('pcntl_fork')) {
                exit('PCNTL functions not available');
            }
            $pid = pcntl_fork();
            if ($pid > 0) {
                return 0;
            }
        }

        return $this->warlock->bootstrap()->run();
    }

    protected function stopServer(Input $input, Output $output): int
    {
        $output->write('Stopping Warlock server...'.PHP_EOL);
        $result = $this->warlock->stop($input->getOption('force') ?? false, $input->getOption('pid') ?? null);
        if (false === $result) {
            $output->write('Failed to stop Warlock server.'.PHP_EOL);

            return 0;
        }
        $output->write('Warlock server stopped.'.PHP_EOL);

        return 1;
    }

    protected function restartServer(Input $input, Output $output): int
    {
        $output->write('Restarting Warlock server...'.PHP_EOL);
        $input->setOption('daemon', true);
        $this->stopServer($input, $output);

        return $this->startServer($input, $output);
    }
}
