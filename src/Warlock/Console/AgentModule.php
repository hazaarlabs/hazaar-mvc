<?php

declare(strict_types=1);

namespace Hazaar\Warlock\Console;

use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\Util\Boolean;
use Hazaar\Warlock\Agent\Main;

class AgentModule extends Module
{
    private Main $agent;

    public function configure(): void
    {
        $this->setName('agent')->setDescription('Warlock Agent Commands');
        $this->addCommand('run', [$this, 'startAgent'])
            ->setDescription('Run the Warlock agent')
            ->addOption(long: 'env', short: 'e', description: 'The environment to run the agent in')
            ->addOption(long: 'config', description: 'The configuration file to use')
            ->addOption(long: 'silent', short: 's', description: 'Run the agent in single process mode')
            // ->addOption(long: 'daemon', short: 'd', description: 'Run the agent in daemon mode')
        ;
        // $this->addCommand('stop', [$this, 'stopAgent'])
        //     ->setDescription('Stop the Warlock agent')
        //     ->addOption('force', short: 'f', description: 'Force stop the Agent')
        //     ->addOption('pid', short: 'p', description: 'The PID file to use')
        // ;
        // $this->addCommand('restart', [$this, 'restartAgent'])
        //     ->setDescription('Restart the Warlock agent')
        //     ->addOption('force', short: 'f', description: 'Force restart the agent')
        //     ->addOption('pid', short: 'p', description: 'The PID file to use')
        // ;
    }

    protected function prepare(Input $input, Output $output): void
    {
        $env = $input->getOption('env') ?? 'development';
        $configFile = $input->getOption('config') ?? getcwd().'/agent.json';
        $this->agent = new Main(configFile: $configFile, env: $env);
    }

    protected function startAgent(Input $input, Output $output): int
    {
        if (true === Boolean::from($input->getOption('silent') ?? false)) {
            $this->agent->setSilent();
        }
        if (true === Boolean::from($input->getOption('daemon') ?? false)) {
            if (!function_exists('pcntl_fork')) {
                exit('PCNTL functions not available');
            }
            $pid = pcntl_fork();
            if (-1 === $pid) {
                exit('Could not fork process');
            }
            if ($pid > 0) {
                return 0;
            }
        }

        return $this->agent->bootstrap()->run();
    }

    // protected function stopAgent(Input $input, Output $output): int
    // {
    //     $output->write('Stopping Warlock agent...'.PHP_EOL);
    //     $result = $this->agent->stop($input->getOption('force') ?? false, $input->getOption('pid') ?? null);
    //     if (false === $result) {
    //         $output->write('Failed to stop Warlock agent.'.PHP_EOL);

    //         return 0;
    //     }
    //     $output->write('Warlock agent stopped.'.PHP_EOL);

    //     return 1;
    // }

    // protected function restartAgent(Input $input, Output $output): int
    // {
    //     $output->write('Restarting Warlock agent...'.PHP_EOL);
    //     $input->setOption('daemon', true);
    //     $this->stopAgent($input, $output);

    //     return $this->startAgent($input, $output);
    // }
}
