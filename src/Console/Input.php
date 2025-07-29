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
    public function initialise(array $argv, array $moduleNames = []): bool
    {
        $this->executable = basename(array_shift($argv));
        if (!current($argv)) {
            $this->module = 'default';
            $this->command = 'help';
            $this->argv = [];

            return true;
        }
        if (0 === count($argv)) {
            $this->argv = [];

            return false;
        }
        while ('-' === substr(current($argv), 0, 1)) {
            $this->parseOption($argv, Command::$globalOptions, $this->globalOptions);
            next($argv);
        }
        $this->module = current($argv);
        if (!in_array($this->module, $moduleNames)) {
            $this->module = 'default';
        } else {
            next($argv);
        }
        $command = current($argv);
        if (false === $command) {
            return false;
        }
        $this->command = $command;
        $this->argv = array_slice($argv, key($argv) + 1);

        return true;
    }

    public function run(Command $command): void
    {
        $this->commandObject = $command;
        $optionsDefinition = $command->getOptions();
        $definedArguments = $command->getArguments();
        $argumentsDefinition = array_keys($definedArguments);
        reset($this->argv);
        do {
            if ($this->parseOption($this->argv, $optionsDefinition, $this->options)) {
                continue;
            }
            if (0 === count($argumentsDefinition)) {
                // No more arguments defined, so we can ignore this one.
                continue;
            }
            $argName = array_shift($argumentsDefinition);
            $arg = $definedArguments[$argName];
            $argValue = current($this->argv);
            if ($arg->required && !$argValue) {
                throw new \InvalidArgumentException(
                    "Argument `{$argName}` is required, but none was provided.\n\nRun `{$this->executable} help {$this->module} {$command->getName()}` for usage information."
                );
            }
            $this->args[$argName] = $argValue;
        } while (false !== next($this->argv));
        // Check if there are boolean options that were not set
        foreach ($optionsDefinition as $option) {
            if ($option->takesValue || array_key_exists($option->long, $this->options)) {
                continue;
            }
            $this->options[$option->long] = $option->default ?? false;
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

    /**
     * Gets all the arguments that were passed to the command.
     *
     * @return array<string>
     */
    public function getArguments(): array
    {
        return $this->args;
    }

    public function getOption(string $name, ?string $default = null): mixed
    {
        if (array_key_exists($name, Command::$globalOptions)) {
            return $this->globalOptions[$name] ?? $default;
        }

        return $this->options[$name] ?? $default;
    }

    /**
     * Gets the options that were set on the command line.
     *
     * @return array<string,mixed> the options
     */
    public function getOptions(): array
    {
        return $this->options;
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
     * Parses a command line option and adds it to the options array.
     *
     * @param array<string,string> $argv
     * @param array<string,Option> $optionsDefinition
     * @param array<string,mixed>  $options
     */
    private function parseOption(array &$argv, array &$optionsDefinition, array &$options): bool
    {
        $arg = current($argv);
        if (false === $arg) {
            return false;
        }
        $offset = 1;
        $equalsValue = null;
        if (str_starts_with($arg, '--')) {
            $offset = 2;
            if (($pos = strpos($arg, '=')) !== false) {
                $equalsValue = trim(substr($arg, $pos + 1));
                $arg = substr($arg, 0, $pos);
            }
        } elseif (!str_starts_with($arg, '-')) {
            return false;
        }
        $key = substr($arg, $offset);
        if (1 === strlen($key)) {
            // Search for an option with a matching short name
            foreach ($optionsDefinition as $o) {
                if (isset($o->short) && $o->short === $key) {
                    $key = $o->long;

                    break;
                }
            }
        }
        if (!array_key_exists($key, $optionsDefinition)) {
            // search for short options
            return true;
        }
        $option = $optionsDefinition[$key];
        if ($option->takesValue) {
            if (null !== $equalsValue) {
                $value = $equalsValue;
            } else {
                $value = $option->default;
                $nextArg = next($argv);
                if (false !== $nextArg && !str_starts_with($nextArg, '-')) {
                    $value = $nextArg;
                }
            }
            if (null === $value) {
                throw new \InvalidArgumentException(
                    "Option `-{$key}` requires a value, but none was provided."
                );
            }
        } else {
            $value = true;
        }
        $options[$option->long] = $value;

        return true;
    }
}
