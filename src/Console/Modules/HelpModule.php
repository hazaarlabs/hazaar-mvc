<?php

declare(strict_types=1);

namespace Hazaar\Console\Modules;

use Hazaar\Console\Argument;
use Hazaar\Console\Command;
use Hazaar\Console\Input;
use Hazaar\Console\Module;
use Hazaar\Console\Option;
use Hazaar\Console\Output;

class HelpModule extends Module
{
    private string $executable;

    public function execute(Input $input, Output $output): int
    {
        $this->executable = $input->getExecutable();
        $commands = [];
        $arguments = [];
        $options = [];
        $argv = $input->getArgv();
        if (0 === count($argv)) {
            $this->writeHelp($output);
        } else {
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
            $argName = array_shift($argv);
            $argv = array_slice($input->getArgv(), 1);
            if (null === ($modules = $moduleList[$argName] ?? null)) {
                foreach ($this->application->modules as $module) {
                    if ($module->getName()) {
                        continue;
                    }
                    $command = $module->getCommand($argName);
                    if (!$command) {
                        continue;
                    }
                    $this->writeCommand($output, $command);
                    $arguments = $command->getArguments();
                    $options = $command->getOptions();

                    break;
                }
            } else {
                $module = $modules[0];
                if (count($argv) > 0) {
                    $command = $module->getCommand($argv[0]);
                    if (null === $command) {
                        $output->write('<fg=red>Command not found: '.$argv[0].'</>'.PHP_EOL);

                        return 1;
                    }
                    $this->writeCommand($output, $command, $module);
                    $arguments = $command->getArguments();
                    $options = $command->getOptions();
                } else {
                    $this->writeModuleHelp($output, $module);
                    foreach ($module->getCommands() as $command) {
                        $name = $command->getName();
                        $commands[$name] = $command->getDescription();
                    }
                    $this->writeCommands($output, $commands);
                }
            }
        }
        $this->writeArguments($output, $arguments);
        $this->writeOptions($output, $options);

        return 0;
    }

    protected function configure(): void
    {
        $this->addCommand('help')
            ->setDescription('Display help information for a command')
            ->addArgument('command', 'The command to display help for')
            ->addGlobalOption('env', 'e', 'The environment to use.  Overrides the APPLICATION_ENV environment variable', true, 'development', valueType: 'env')
        ;
    }

    private function writeGlobalOptions(Output $output): void
    {
        if (0 === count(Command::$globalOptions)) {
            return;
        }
        $this->writeOptions($output, Command::$globalOptions, 'Global Options');
        $output->write(PHP_EOL);
    }

    /**
     * Writes the available commands to the output.
     *
     * @param array<string,string> $commands
     */
    private function writeCommands(Output $output, array $commands): void
    {
        if (0 === count($commands)) {
            return;
        }
        ksort(array: $commands);
        $pad = min(15, max(array_map(callback: 'strlen', array: array_keys($commands)))) + 5;
        $output->write('<fg=green>Available Commands:</>'.PHP_EOL);
        foreach ($commands as $command => $description) {
            $output->write('  '.str_pad($command, $pad, ' ', STR_PAD_RIGHT).$description.PHP_EOL);
        }
        $output->write(PHP_EOL);
    }

    private function writeCommand(Output $output, Command $command, ?Module $module = null): void
    {
        $options = $command->getOptions();
        $output->write('<fg=yellow>'.$command->getDescription().'</>'.PHP_EOL.PHP_EOL);
        $output->write("<fg=blue>Usage:</> {$this->executable} {$module?->getName()} {$command->getName()}");
        if (count($options) > 0) {
            $output->write(' [options]');
        }
        foreach ($command->getArguments() as $argument) {
            $output->write(' <fg=green>['.$argument->name.']</>');
        }
        $output->write(PHP_EOL);
    }

    /**
     * Writes the arguments for a command to the output.
     *
     * @param array<Argument> $arguments
     */
    private function writeArguments(Output $output, array $arguments): void
    {
        if (0 === count($arguments)) {
            return;
        }
        ksort(array: $arguments);
        $pad = min(15, max(array_map(callback: fn ($a) => strlen($a->name), array: $arguments))) + 5;
        $output->write(PHP_EOL.'<fg=yellow>Arguments:</>'.PHP_EOL);
        foreach ($arguments as $argument) {
            $output->write('  <fg=green>'.str_pad($argument->name, $pad, ' ').'</>'.$argument->description.PHP_EOL);
        }
    }

    /**
     * Writes the options for a command to the output.
     *
     * @param array<Option> $options
     */
    private function writeOptions(Output $output, array $options, string $label = 'Options'): void
    {
        if (0 === count($options)) {
            return;
        }
        $output->write(PHP_EOL."<fg=yellow>{$label}:</>".PHP_EOL);
        $padOption = min(15, max(array_map(callback: fn ($o) => strlen($o->long) + strlen($o->short ?? ''), array: $options))) + 5;
        $padValueType = max(array_map(callback: fn ($o) => strlen($o->valueType ?? ''), array: $options)) + 2;
        foreach ($options as $option) {
            $label = '--'.$option->long;
            if (null !== $option->short) {
                $label .= ', -'.$option->short;
            }
            $output->write('  <fg=green>'.str_pad($label, $padOption, ' ').'</>');
            if ($option->takesValue) {
                $valueType = strtoupper($option->valueType ?? 'VALUE');
                $valueType = str_pad("&lt;{$valueType}&gt;", $padValueType + 6, ' ', STR_PAD_RIGHT);
                $output->write(" <fg=green>{$valueType}</>");
            } else {
                $output->write(str_repeat(' ', $padValueType + 1));
            }
            $output->write('    '.$option->description.PHP_EOL);
        }
    }

    private function writeModuleHelp(Output $output, Module $module): void
    {
        $cli = '';
        if (Command::$globalOptions) {
            $cli .= '[globals] ';
        }
        $cli .= $module->getName().' [command] [options]';
        $output->write('<fg=yellow>'.$module->getDescription().'</>'.PHP_EOL.PHP_EOL);
        $output->write("<fg=green>Usage: {$this->executable} {$cli}</>".PHP_EOL);
        $output->write(PHP_EOL);
        $this->writeGlobalOptions($output);
    }

    private function writeHelp(Output $output): void
    {
        $commands = [];
        $output->write('<fg=yellow>Hazaar Console Application</>'.PHP_EOL.PHP_EOL);
        $cli = '';
        if (Command::$globalOptions) {
            $cli .= '[GLOBAL OPTIONS]';
        }
        $cli .= ' <command> [COMMAND OPTIONS]';
        $output->write("<fg=green>Usage: {$this->executable} {$cli}</>".PHP_EOL.PHP_EOL);
        $this->writeGlobalOptions($output);
        foreach ($this->application->modules as $module) {
            $moduleName = $module->getName();
            if ($moduleName) {
                $commands[$moduleName] = $module->getDescription();
            } else {
                foreach ($module->getCommands() as $command) {
                    $name = $command->getName();
                    $commands[$name] = $command->getDescription();
                }
            }
        }
        $this->writeCommands($output, $commands);
    }
}
