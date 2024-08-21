<?php
/**
 * @file        Hazaar/Application/Request/Cli.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application\Request;

class Cli extends \Hazaar\Application\Request
{
    private $options = [];

    private $commands = [];

    private static $opt;

    public function init($args)
    {
        $this->params = $args;
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
     */
    public function setCommands($commands)
    {
        $this->commands = $commands;
    }

    /**
     * Retrieves the argument at the specified index from the command line arguments.
     *
     * @param int $index The index of the argument to retrieve.
     * @param mixed $default The default value to return if the argument is not found. Default is null.
     * @return mixed The value of the argument at the specified index, or the default value if the argument is not found.
     */
    public function argv($index, $default = null) {

        if(array_key_exists($index, $this->params))
            return $this->params[$index];

        return $default;

    }

    /**
     * Get the number of command-line arguments.
     *
     * This method returns the count of the command-line arguments passed to the script.
     *
     * @return int The number of command-line arguments.
     */
    public function argc() {

        return count($this->params);

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
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * Gets the command that was used on the command line if it exists.
     * 
     * @param array $args An array of arguments that were specified after the command.
     * 
     * @return string The name of the command.
     */
    public function getCommand(&$args = null)
    {
        $this->getOptions($pos_args);
        $command = array_shift($pos_args);
        if (!array_key_exists($command, $this->commands)) {
            return null;
        }
        $args = $pos_args;
        return $command;
    }

    /**
     * Returns the currently applied options from the ARGV command line options.
     */
    public function getOptions(&$pos_args = null)
    {
        if (!self::$opt) {
            self::$opt = [0 => '', 1 => []];

            foreach ($this->options as $name => $o) {
                if ($o[0]) {
                    self::$opt[0] .= $o[0] . (is_string($o[2]) ? ':' : '');
                }

                if ($o[1]) {
                    self::$opt[1][] = $o[1] . (is_string($o[2]) ? ':' : '');
                }
            }
        }

        $ops = getopt(self::$opt[0], self::$opt[1], $rest_index);

        $pos_args = array_slice($_SERVER['argv'], $rest_index);

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
    public function showHelp()
    {
        $pad = 30;

        $script = basename(coalesce(ake($_SERVER, 'CLI_COMMAND'), ake($_SERVER, 'SCRIPT_FILENAME')));

        $msg = "Syntax: $script";

        if (is_array($this->options) && count($this->options) > 0) {
            $msg .= ' [options]';
        }

        if (is_array($this->commands) && count($this->commands) > 0) {
            $msg .= ' [command]';
        }

        if (is_array($this->options) && count($this->options) > 0) {
            $msg .= "\n\nGlobal Options:\n\n";
            foreach ($this->options as $o) {
                if (ake($o, 4)) {
                    continue;
                }

                $avail = [];

                if ($o[0]) {
                    $avail[] = '-' . $o[0] . (is_string($o[2]) ? ' ' . $o[2] : '');
                }

                if ($o[1]) {
                    $avail[] = '--' . $o[1] . (is_string($o[2]) ? '=' . $o[2] : '');
                }

                $msg .= '  ' . str_pad(implode(', ', $avail), $pad, ' ', STR_PAD_RIGHT) . ' ' . ake($o, 3) . "\n";
            }
        }

        $options_msg = [];

        if (is_array($this->commands) && count($this->commands) > 0) {
            $msg .= "\nCommands:\n\n";
            foreach ($this->commands as  $cmd => $c) {
                $name = $cmd;
                if ($options = ake($c, 1)) {
                    if (!is_array($options)) {
                        $options = [$options];
                    }
                    $name .= ' [' . implode('], [', $options) . ']';
                }
                $msg .= '  ' . str_pad($name, $pad, ' ', STR_PAD_RIGHT) . ' ' . ake($c, 0) . "\n";
                foreach ($this->options as $o) {
                    if (ake($o, 4) !== $cmd) {
                        continue;
                    }

                    $avail = [];

                    if ($o[0]) {
                        $avail[] = '-' . $o[0] . (is_string($o[2]) ? ' ' . $o[2] : '');
                    }

                    if ($o[1]) {
                        $avail[] = '--' . $o[1] . (is_string($o[2]) ? '=' . $o[2] : '');
                    }

                    $options_msg[$cmd][] = '    ' . str_pad(implode(", ", $avail), $pad-2, ' ', STR_PAD_RIGHT) . ' ' . ake($o, 3) . "\n";
                }
            }
        }

        if (count($options_msg) > 0) {
            $msg .= "\nCommand Options:\n\n";
            foreach ($options_msg as $cmd => $options) {
                $msg .= "  $cmd:\n\n".implode("\n", $options) . "\n";
            }
        }

        echo $msg . "\n";

        return 0;
    }
}
