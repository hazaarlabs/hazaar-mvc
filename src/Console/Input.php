<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Request/Cli.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Console;

class Input
{
    private string $executable;

    /**
     * @var array<mixed>
     */
    private array $argv;

    /**
     * @var array<mixed>
     */
    private array $args = [];

    /**
     * @var array<string,mixed>
     */
    private array $globalOptions = [];

    /**
     * @var array<string,mixed>
     */
    private array $options = [];

    private ?string $command = null;

    private ?Command $commandObject = null;

    /**
     * Initialises the input object with the command line arguments.
     *
     * @param array<mixed> $argv
     */
    public function initialise(array $argv): void
    {
        $this->executable = basename(array_shift($argv));
        if (!current($argv)) {
            return;
        }
        $definedOptions = $this->reduceOptions(Command::$globalOptions);
        if (0 === count($argv)) {
            $this->argv = [];

            return;
        }
        while ('-' === substr(current($argv), 0, 1)) {
            $this->parseOption(current($argv), $definedOptions, $this->globalOptions);
            next($argv);
        }
        $this->command = current($argv);
        $this->argv = array_slice($argv, key($argv) + 1);
    }

    public function run(Command $command): void
    {
        $this->commandObject = $command;
        $optionsDefinition = $this->reduceOptions($command->getOptions());
        $definedArguments = $command->getArguments();
        $argumentsDefinition = [];
        foreach ($definedArguments as $def) {
            $argumentsDefinition[] = $def['name'];
        }
        foreach ($this->argv as $arg) {
            if ($this->parseOption($arg, $optionsDefinition, $this->options)) {
                continue;
            }
            $argName = array_shift($argumentsDefinition);
            $this->args[$argName] = $arg;
        }
    }

    public function getExecutable(): string
    {
        return $this->executable;
    }

    public function getGlobalOption(string $name): mixed
    {
        return ake($this->globalOptions, $name);
    }

    public function getCommandObject(): ?Command
    {
        return $this->commandObject;
    }

    /**
     * Gets the command that was used on the command line if it exists.
     *
     * @return null|string the name of the command
     */
    public function getCommand(): ?string
    {
        return $this->command;
    }

    public function getArgument(string $name): ?string
    {
        return ake($this->args, $name);
    }

    public function getOption(string $name): mixed
    {
        return ake($this->options, $name);
    }

    /**
     * Reduces the options array to a simple array of option names.
     *
     * @param array<array{long: string, short: null|string, description: null|string, required: bool}> $definedOptions
     *
     * @return array<string>
     */
    private function reduceOptions(array $definedOptions): array
    {
        $optionsDefinition = [];
        foreach ($definedOptions as $def) {
            $optionsDefinition[] = $def['long'];
            if ($def['short']) {
                $definition[] = $def['short'];
            }
        }

        return $optionsDefinition;
    }

    /**
     * Parses a command line option and adds it to the options array.
     *
     * @param array<string,mixed> $optionsDefinition
     * @param array<string,mixed> $options
     */
    private function parseOption(string $arg, array &$optionsDefinition, array &$options): bool
    {
        if (str_starts_with($arg, '--')) {
            $eq = strpos($arg, '=');
            $key = substr($arg, 2, $eq ? $eq - 2 : null);
            if (in_array($key, $optionsDefinition)) {
                $value = $eq ? substr($arg, $eq + 1) : true;
                $options[$key] = $value;
            }

            return true;
        }
        if (str_starts_with($arg, '-')) {
            $key = substr($arg, 1, 1);
            if (in_array($key, $optionsDefinition)) {
                $value = substr($arg, 2);
                $options[$key] = $value;
            }

            return true;
        }

        return false;
    }

    // Shows a help page on the CLI for the options and commands that have been configured.
    // public function showHelp(): int
    // {
    //     $pad = 30;
    //     $script = basename(coalesce(ake($_SERVER, 'CLI_COMMAND'), ake($_SERVER, 'SCRIPT_FILENAME')));
    //     $msg = "Syntax: {$script}";
    //     if (count($this->options) > 0) {
    //         $msg .= ' [options]';
    //     }
    //     if (count($this->commands) > 0) {
    //         $msg .= ' [command]';
    //     }
    //     if (count($this->options) > 0) {
    //         $msg .= "\n\nGlobal Options:\n\n";
    //         foreach ($this->options as $o) {
    //             if (ake($o, 4)) {
    //                 continue;
    //             }
    //             $avail = [];
    //             if ($o[0]) {
    //                 $avail[] = '-'.$o[0].(is_string($o[2]) ? ' '.$o[2] : '');
    //             }
    //             if ($o[1]) {
    //                 $avail[] = '--'.$o[1].(is_string($o[2]) ? '='.$o[2] : '');
    //             }
    //             $msg .= '  '.str_pad(implode(', ', $avail), $pad, ' ', STR_PAD_RIGHT).' '.ake($o, 3)."\n";
    //         }
    //     }
    //     $optionsMsg = [];
    //     if (count($this->commands) > 0) {
    //         $msg .= "\nCommands:\n\n";
    //         foreach ($this->commands as $cmd => $c) {
    //             $name = $cmd;
    //             if ($options = ake($c, 1)) {
    //                 if (!is_array($options)) {
    //                     $options = [$options];
    //                 }
    //                 $name .= ' ['.implode('], [', $options).']';
    //             }
    //             $msg .= '  '.str_pad($name, $pad, ' ', STR_PAD_RIGHT).' '.ake($c, 0)."\n";
    //             foreach ($this->options as $o) {
    //                 if (ake($o, 4) !== $cmd) {
    //                     continue;
    //                 }
    //                 $avail = [];
    //                 if ($o[0]) {
    //                     $avail[] = '-'.$o[0].(is_string($o[2]) ? ' '.$o[2] : '');
    //                 }
    //                 if ($o[1]) {
    //                     $avail[] = '--'.$o[1].(is_string($o[2]) ? '='.$o[2] : '');
    //                 }
    //                 $optionsMsg[$cmd][] = '    '.str_pad(implode(', ', $avail), $pad - 2, ' ', STR_PAD_RIGHT).' '.ake($o, 3)."\n";
    //             }
    //         }
    //     }
    //     if (count($optionsMsg) > 0) {
    //         $msg .= "\nCommand Options:\n\n";
    //         foreach ($optionsMsg as $cmd => $options) {
    //             $msg .= "  {$cmd}:\n\n".implode("\n", $options)."\n";
    //         }
    //     }
    //     echo $msg."\n";

    //     return 0;
    // }
}
