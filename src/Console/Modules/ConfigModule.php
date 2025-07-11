<?php

declare(strict_types=1);

namespace Hazaar\Console\Modules;

use Hazaar\Application;
use Hazaar\Application\Config;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;
use Hazaar\Loader;
use Hazaar\Util\Arr;

class ConfigModule extends Module
{
    public function prepareApp(Input $input, Output $output): int
    {
        $applicationPath = $input->getOption('path');
        if (!$applicationPath || '/' !== substr(trim($applicationPath), 0, 1)) {
            $searchResult = Application::findAppPath($applicationPath);
            if (null === $searchResult) {
                $output->write('Application path not found: '.$applicationPath.PHP_EOL);

                return 1;
            }
            $applicationPath = $searchResult;
            Loader::createInstance($applicationPath);
        }

        return 0;
    }

    protected function configure(): void
    {
        $this->application->registerMethod([$this, 'prepareApp']);
        $this->setName('config')->setDescription('View or modify the application configuration');
        $this->addCommand('show', [$this, 'showConfig'])
            ->setDescription('Show the application configuration')
        ;
        $this->addCommand('check', [$this, 'checkConfig'])
            ->setDescription('Check the application configuration')
        ;
        $this->addCommand('get', [$this, 'getConfigItem'])
            ->setDescription('View the application configuration')
            ->addArgument('option', 'The configuration option to view', true)
        ;
        $this->addCommand('set', [$this, 'setConfigItem'])
            ->setDescription('Set the application configuration')
            ->addArgument('option', 'The configuration option to set in the format key=value;key2=value2', true)
            ->addArgument('value', 'The value to set the configuration option to', false)
        ;
    }

    protected function showConfig(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? constant('APPLICATION_ENV') ?: 'development';
        $config = Config::getInstance('application', $env);
        $output->write('app.env = '.$env.PHP_EOL);
        $list = Arr::toDotNotation($config->toArray());
        foreach ($list as $key => $value) {
            $output->write($key.' = '.$value.PHP_EOL);
        }

        return 0;
    }

    protected function getConfigItem(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? constant('APPLICATION_ENV') ?: 'development';
        $config = Config::getInstance('application', $env);
        if (!($configArg = $input->getArgument('option'))) {
            throw new \InvalidArgumentException('No configuration argument specified', 1);
        }
        $value = Arr::get($config, $configArg);
        if (is_array($value)) {
            $value = "{$configArg}.".Arr::flatten(Arr::toDotNotation($value), ' = ', "\n{$configArg}.");
        } else {
            $value = $configArg.' = '.$value;
        }
        $output->write($value.PHP_EOL);

        return 0;
    }

    protected function setConfigItem(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? constant('APPLICATION_ENV') ?: 'development';
        $config = Config::getInstance('application', $env);
        if (!($configArg = $input->getArgument('config'))) {
            throw new \InvalidArgumentException('No configuration argument specified', 1);
        }
        $configUpdates = Arr::unflatten($configArg, '=', ';');
        if (0 === count($configUpdates)) {
            throw new \InvalidArgumentException('No configuration value specified', 1);
        }
        foreach ($configUpdates as $key => $value) {
            $config[$key] = $value;
        }
        if (false === $config->save()) {
            throw new \InvalidArgumentException('Failed to save configuration', 1);
        }

        return 0;
    }

    protected function checkConfig(Input $input, Output $output): int
    {
        $env = $input->getOption('env') ?? constant('APPLICATION_ENV') ?: 'development';
        $config = Config::getInstance('application', $env);
        if (!$config->count() > 0) {
            $output->write('No configuration found for environment: '.$env.PHP_EOL);

            return 1;
        }
        $output->write('Configuration found'.PHP_EOL);

        return 0;
    }
}
