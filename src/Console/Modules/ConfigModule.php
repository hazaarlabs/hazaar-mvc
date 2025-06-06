<?php

declare(strict_types=1);

namespace Hazaar\Console\Modules;

use Hazaar\Application\Config;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\Util\Arr;

class ConfigModule extends Module
{
    protected function configure(): void
    {
        $this->addCommand('config')
            ->setDescription('View or modify the application configuration')
            ->addArgument('command', 'The tool command to run')
            ->addArgument('args', 'Arguments to pass to the tool command', true)
        ;
    }

    protected function execute(Input $input, Output $output): int
    {
        $configCommand = $input->getArgument('command') ?? 'show';
        $env = $input->getOption('env') ?? defined('APPLICATION_ENV') ? APPLICATION_ENV : 'development';
        $config = Config::getInstance('application', $env);

        switch ($configCommand) {
            case 'get':
                if (!($configArg = $input->getArgument('config'))) {
                    throw new \Exception('No configuration argument specified', 1);
                }
                $value = $config[$configArg];
                $output->write($configArg.'='.$value.PHP_EOL);

                break;

            case 'set':
                if (!($configArg = $input->getArgument('config'))) {
                    throw new \Exception('No configuration argument specified', 1);
                }
                $configUpdates = Arr::unflatten($configArg, '=', ';');
                if (0 === count($configUpdates)) {
                    throw new \Exception('No configuration value specified', 1);
                }
                foreach ($configUpdates as $key => $value) {
                    $config[$key] = $value;
                }
                if (false === $config->save()) {
                    throw new \Exception('Failed to save configuration', 1);
                }

                break;

            case 'show':
                $output->write('app.env = '.$env.PHP_EOL);
                $list = Arr::toDotNotation($config->toArray());
                foreach ($list as $key => $value) {
                    $output->write($key.' = '.$value.PHP_EOL);
                }

                break;
        }

        return 0;
    }
}
