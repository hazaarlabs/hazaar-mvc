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
        $this->addCommand('run')
            ->setDescription('Run the Warlock server')
            ->addOption(long: 'env', short: 'e', description: 'The environment to run the server in')
            ->addOption(long: 'config', description: 'The configuration file to use')
            ->addOption(long: 'silent', short: 's', description: 'Run the server in single process mode')
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? 'development';
        $configFile = $input->getOption('config') ?? getcwd().'/warlock.json';
        $this->warlock = new Main(configFile: $configFile, env: $env);
        if (true === Boolean::from($input->getOption('s') ?? false)) {
            $this->warlock->setSilent(true);
        }

        return $this->warlock->bootstrap()->run();
    }
}
