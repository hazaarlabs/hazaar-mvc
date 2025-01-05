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
    private array $options = [];

    private ?string $command = null;

    private ?Command $commandObject = null;

    public function __construct()
    {
        $this->argv = array_slice((array) $_SERVER['argv'], 1);
        $this->command = array_shift($this->argv);
    }

    public function initialise(Command $command): void
    {
        $this->commandObject = $command;
        $definedOptions = $command->getOptions();
        $optionsDefinition = [];
        foreach ($definedOptions as $def) {
            $optionsDefinition[] = $def['long'];
            if ($def['short']) {
                $definition[] = $def['short'];
            }
        }
        $definedArguments = $command->getArguments();
        $argumentsDefinition = [];
        foreach ($definedArguments as $def) {
            $argumentsDefinition[] = $def['name'];
        }
        foreach ($this->argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $eq = strpos($arg, '=');
                $key = substr($arg, 2, $eq ? $eq - 2 : null);
                if (!in_array($key, $optionsDefinition)) {
                    continue;
                }
                $value = $eq ? substr($arg, $eq + 1) : true;
                $this->options[$key] = $value;
            } elseif (str_starts_with($arg, '-')) {
                $key = substr($arg, 1, 1);
                if (!in_array($key, $optionsDefinition)) {
                    continue;
                }
                $value = substr($arg, 2);
                $this->options[$key] = $value;
            } else {
                $argName = array_shift($argumentsDefinition);
                $this->args[$argName] = $arg;
            }
        }
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
