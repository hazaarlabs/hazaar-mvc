<?php

declare(strict_types=1);

namespace Hazaar\Console\Modules;

use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Output;

class HelpModule extends Module
{
    public function execute(Input $input, Output $output): int
    {
        $executable = $input->getExecutable();
        $cli = '';
        if (Command::$globalOptions) {
            $cli .= '[globals]';
        }
        $cli .= ' [command] [options]';
        $output->write("<fg=green>Usage: {$executable} {$cli}</>".PHP_EOL);
        $output->write(PHP_EOL);
        if (count(Command::$globalOptions) > 0) {
            $output->write('<fg=green>Global Options:</>'.PHP_EOL);
            foreach (Command::$globalOptions as $option) {
                $output->write('  <fg=green>--'.$option['long'].'</>');
                if (null !== $option['short']) {
                    $output->write(message: ', <fg=green>-'.$option['short'].'</>');
                }
                $output->write(' - '.$option['description'].PHP_EOL);
            }
            $output->write(PHP_EOL);
        }
        $moduleList = [];
        // Index modules by name
        foreach ($this->application->modules as $module) {
            if (!($moduleName = $module->getName())) {
                continue;
            }
            if (!isset($moduleList[$moduleName])) {
                $moduleList[$moduleName] = [];
            }
            $moduleList[$moduleName][] = $module;
        }
        $commands = [];
        $arguments = [];
        $options = [];
        $argv = $input->getArgv();
        if (count($argv) > 0) {
            $argName = array_shift($argv);
            $argv = array_slice($input->getArgv(), 1);
            if (null === ($modules = $moduleList[$argName] ?? null)) {
                foreach ($this->application->modules as $module) {
                    if ($module->getName()) {
                        continue;
                    }
                    if ($command = $module->getCommand($argName)) {
                        $arguments = $command->getArguments();
                        $options = $command->getOptions();
                        $output->write('<fg=yellow>'.$command->getDescription().'</>'.PHP_EOL.PHP_EOL);
                        $output->write("<fg=blue>Usage:</> {$executable} {$command->getName()}");
                        if (count($options) > 0) {
                            $output->write(' [options]');
                        }
                        foreach ($command->getArguments() as $argument) {
                            $output->write(' <fg=green>['.$argument['name'].']</>');
                        }
                        $output->write(PHP_EOL);
                    }
                }
                if (0 === count($arguments)) {
                    $output->write('<fg=red>Command not found: '.$argName.'</>'.PHP_EOL);

                    return 1;
                }
            } else {
                foreach ($modules as $module) {
                    if (count($argv) > 0) {
                        $command = $module->getCommand($argv[0]);
                        if (null === $command) {
                            $output->write('<fg=red>Command not found: '.$argv[0].'</>'.PHP_EOL);

                            return 1;
                        }
                        $arguments = $command->getArguments();
                        $options = $command->getOptions();
                        $output->write('<fg=yellow>'.$command->getDescription().'</>'.PHP_EOL.PHP_EOL);
                        $output->write("<fg=blue>Usage:</> {$executable} {$module->getName()} {$command->getName()}");
                        if (count($options) > 0) {
                            $output->write(' [options]');
                        }
                        foreach ($command->getArguments() as $argument) {
                            $output->write(' <fg=green>['.$argument['name'].']</>');
                        }
                        $output->write(PHP_EOL);

                        break;
                    }
                    foreach ($module->getCommands() as $command) {
                        $name = $command->getName();
                        $commands[$name] = '  '.$name.' - '.$command->getDescription();
                    }
                }
            }
        } else {
            foreach ($this->application->modules as $module) {
                $moduleName = $module->getName();
                if ($moduleName) {
                    $commands[$moduleName] = '  '.$moduleName.' - '.$module->getDescription();
                } else {
                    foreach ($module->getCommands() as $command) {
                        $name = $command->getName();
                        $commands[$name] = '  '.$name.' - '.$command->getDescription();
                    }
                }
            }
        }
        if (count($commands) > 0) {
            ksort(array: $commands);
            $output->write('<fg=green>Available Commands:</>'.PHP_EOL);
            foreach ($commands as $command) {
                $output->write($command.PHP_EOL);
            }
            $output->write(PHP_EOL);
        }
        if (count($arguments) > 0) {
            ksort(array: $arguments);
            $output->write(PHP_EOL.'<fg=yellow>Arguments:</>'.PHP_EOL);
            foreach ($arguments as $argument) {
                $output->write('  <fg=green>'.$argument['name'].'</> - '.$argument['description'].PHP_EOL);
            }
        }
        if (count($options) > 0) {
            $output->write(PHP_EOL.'<fg=yellow>Options:</>'.PHP_EOL);
            foreach ($options as $option) {
                $output->write('  <fg=green>--'.$option['long'].'</>');
                if (null !== $option['short']) {
                    $output->write(', <fg=green>-'.$option['short'].'</>');
                }
                $output->write(' - '.$option['description'].PHP_EOL);
            }
        }

        return 0;
    }

    protected function configure(): void
    {
        $this->addCommand('help')
            ->setDescription('Display help information for a command')
            ->addArgument('command', 'The command to display help for')
            ->addGlobalOption('env', 'e', 'The environment to use.  Overrides the APPLICATION_ENV environment variable')
        ;
    }
}
