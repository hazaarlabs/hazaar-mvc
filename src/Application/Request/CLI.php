<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Request/Cli.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application\Request;

use Hazaar\Application\Request;

class CLI extends Request
{
    /**
     * @var array<mixed>
     */
    private array $options = [];

    /**
     * @var array<mixed>
     */
    private array $commands = [];

    /**
     * @var array<mixed>
     */
    private static array $opt = [];

    /**
     * @param array<string> $args
     */
    public function init(array $args): string
    {
        $this->params = $args;

        return '/';
    }

    /**
     * Sets the available commands.
     *
     * Format of `$commands` is an array where the key is the name of the command and:
     *  * Index 0 is the help description of the command.
     *  * Index 1 is a string or array of optional parameters.
     *
     * # Example:
     * ```php
     * $cli->SetCommands([
     *      'test' => ['Execute a test', ['when']],
     *      'exit' => ['Exit the CLI']
     * ]);
     * ```
     *
     * If a command is specified on the CLI when executing the program it will be returned with `$cli->getCommand()`.
     *
     * @param array<mixed> $commands
     */
    public function setCommands(array $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * Sets the available options.
     *
     * Format of `$options` is an array where the key is the name of the option and:
     *  * Index 0 is the 'short name' of the option.  See: getopts().
     *  * Index 1 is the 'long name' of the option.  See: getopts().
     *  * Index 2 is the name of an optional parameter.
     *  * Index 3 is the help description displayed on the help page.
     *  * Index 4 is the name of a command that the options is limited to. Optional.
     *
     * # Example:
     * ```php
     * $cli->setOptions([
     *   'help'     => ['h', 'help', null, 'Display this help message.'],
     *   'timeout'  => ['t', 'timeout', 'seconds', 'Enable test mode.'],
     *   'force'    => ['f', 'force', null, 'Force things to happen.', 'command'],
     * ])
     * ```
     *
     * Options are then available using `$cli->getOptions()` which will return an array of the options
     * specified on the CLI.  If they have a parameter, that parameter will be the value, otherwise the
     * value will be TRUE.
     *
     * @param array<mixed> $options
     */
    public function setOptions($options): void
    {
        $this->options = $options;
    }

    /**
     * Gets the command that was used on the command line if it exists.
     *
     * @param array<string> $args an array of arguments that were specified after the command
     *
     * @return string the name of the command
     */
    public function getCommand(?array &$args = null): ?string
    {
        $this->getOptions($posArgs);
        $command = array_shift($posArgs);
        if (!array_key_exists($command, $this->commands)) {
            return null;
        }
        $args = $posArgs;

        return $command;
    }

    /**
     * Returns the currently applied options from the ARGV command line options.
     *
     * @param null|mixed $posArgs
     *
     * @return array<mixed>
     */
    public function getOptions(&$posArgs = null): array
    {
        if (!self::$opt) {
            self::$opt = [0 => '', 1 => []];
            foreach ($this->options as $name => $o) {
                if ($o[0]) {
                    self::$opt[0] .= $o[0].(is_string($o[2]) ? ':' : '');
                }
                if ($o[1]) {
                    self::$opt[1][] = $o[1].(is_string($o[2]) ? ':' : '');
                }
            }
        }
        $ops = getopt(self::$opt[0], self::$opt[1], $restIndex);
        $posArgs = array_slice($_SERVER['argv'], $restIndex);
        $options = [];
        foreach ($this->options as $name => $o) {
            $s = $l = false;
            $sk = $lk = null;
            if (($o[0] && ($s = array_key_exists($sk = rtrim($o[0], ':'), $ops))) || ($o[1] && ($l = array_key_exists($lk = rtrim($o[1], ':'), $ops)))) {
                $options[$name] = is_string($o[2]) ? ($s ? $ops[$sk] : $ops[$lk]) : true;
            }
        }

        return $options;
    }

    /**
     * Shows a help page on the CLI for the options and commands that have been configured.
     */
    public function showHelp(): int
    {
        $pad = 30;
        $script = basename(coalesce(ake($_SERVER, 'CLI_COMMAND'), ake($_SERVER, 'SCRIPT_FILENAME')));
        $msg = "Syntax: {$script}";
        if (count($this->options) > 0) {
            $msg .= ' [options]';
        }
        if (is_array($this->commands) && count($this->commands) > 0) {
            $msg .= ' [command]';
        }
        if (count($this->options) > 0) {
            $msg .= "\n\nGlobal Options:\n\n";
            foreach ($this->options as $o) {
                if (ake($o, 4)) {
                    continue;
                }
                $avail = [];
                if ($o[0]) {
                    $avail[] = '-'.$o[0].(is_string($o[2]) ? ' '.$o[2] : '');
                }
                if ($o[1]) {
                    $avail[] = '--'.$o[1].(is_string($o[2]) ? '='.$o[2] : '');
                }
                $msg .= '  '.str_pad(implode(', ', $avail), $pad, ' ', STR_PAD_RIGHT).' '.ake($o, 3)."\n";
            }
        }
        $optionsMsg = [];
        if (count($this->commands) > 0) {
            $msg .= "\nCommands:\n\n";
            foreach ($this->commands as $cmd => $c) {
                $name = $cmd;
                if ($options = ake($c, 1)) {
                    if (!is_array($options)) {
                        $options = [$options];
                    }
                    $name .= ' ['.implode('], [', $options).']';
                }
                $msg .= '  '.str_pad($name, $pad, ' ', STR_PAD_RIGHT).' '.ake($c, 0)."\n";
                foreach ($this->options as $o) {
                    if (ake($o, 4) !== $cmd) {
                        continue;
                    }
                    $avail = [];
                    if ($o[0]) {
                        $avail[] = '-'.$o[0].(is_string($o[2]) ? ' '.$o[2] : '');
                    }
                    if ($o[1]) {
                        $avail[] = '--'.$o[1].(is_string($o[2]) ? '='.$o[2] : '');
                    }
                    $optionsMsg[$cmd][] = '    '.str_pad(implode(', ', $avail), $pad - 2, ' ', STR_PAD_RIGHT).' '.ake($o, 3)."\n";
                }
            }
        }
        if (count($optionsMsg) > 0) {
            $msg .= "\nCommand Options:\n\n";
            foreach ($optionsMsg as $cmd => $options) {
                $msg .= "  {$cmd}:\n\n".implode("\n", $options)."\n";
            }
        }
        echo $msg."\n";

        return 0;
    }
}
