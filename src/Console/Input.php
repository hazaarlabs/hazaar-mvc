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

    private ?string $module = null;

    private ?string $command = null;

    private ?Command $commandObject = null;

    /**
     * Initialises the input object with the command line arguments.
     *
     * @param array<mixed>  $argv
     * @param array<string> $moduleNames
     */
    public function initialise(array $argv, array $moduleNames = []): void
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
        $this->module = current($argv);
        if (!in_array($this->module, $moduleNames)) {
            $this->module = 'default';
        } else {
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
        return $this->globalOptions[$name] ?? null;
    }

    public function getModule(): ?string
    {
        return $this->module;
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
        return $this->args[$name] ?? null;
    }

    public function getOption(string $name, ?string $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     * Gets the options that were used on the command line.
     *
     * @return array<string> the options
     */
    public function getArgv(): array
    {
        return $this->argv ?? [];
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
            $optionsDefinition[$def['long']] = $def['long'];
            if ($def['short']) {
                $optionsDefinition[$def['short']] = $def['long'];
            }
        }

        return $optionsDefinition;
    }

    /**
     * Parses a command line option and adds it to the options array.
     *
     * @param array<string,string> $optionsDefinition
     * @param array<string,mixed>  $options
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
        if (!str_starts_with($arg, '-')) {
            return false;
        }
        $key = substr($arg, 1, 1);
        if (array_key_exists($key, $optionsDefinition)) {
            $value = (strlen($arg) > 2) ? substr($arg, 3) : true;
            $options[$optionsDefinition[$key]] = $value;
        }

        return true;
    }
}
