<?php

declare(strict_types=1);

namespace Hazaar\Console;

use Hazaar\Console\Modules\HelpModule;

class Application
{
    private string $name;
    private string $version;

    /**
     * A list of commands registered with the application by modules.
     *
     * @var array<Module>
     */
    private array $commands = [];

    /**
     * @var array<Module>
     */
    private array $modules = [];

    private Input $input;
    private Output $output;

    public function __construct(string $name = 'UNKNOWN', string $version = 'UNKNOWN')
    {
        $this->name = $name;
        $this->version = $version;
        $this->input = new Input();
        $this->output = new Output();
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'shutdownHandler']);
        $this->add(new HelpModule());
    }

    public function add(Module $module): void
    {
        $this->modules[] = $module;
    }

    public function run(): int
    {
        foreach ($this->modules as $module) {
            $module->initialise($this, $this->input, $this->output);
            $modules = array_fill(0, count($module->getCommands()), $module);
            $this->commands = array_merge($this->commands, array_combine(array_keys($module->getCommands()), $modules));
        }
        $this->input->initialise((array) $_SERVER['argv']);
        $this->output->write('<fg=green>'.$this->name.' v'.$this->version.'</>'.PHP_EOL);
        define('APPLICATION_ENV', $this->input->getGlobalOption('env') ?? getenv('APPLICATION_ENV') ?: 'development');
        $this->output->write('<fg=green>Environment: '.APPLICATION_ENV.'</>'.PHP_EOL);
        $commandName = $this->input->getCommand();
        if (!array_key_exists($commandName, $this->commands)) {
            $this->writeHelp($this->output);

            return 1;
        }
        $module = $this->commands[$commandName];
        $code = $module->run($commandName);
        if (-1 === $code) {
            $this->writeHelp($this->output);
            $code = 1;
        }

        return $code;
    }

    public function writeHelp(Output $output): void
    {
        $cli = $this->input->getExecutable();
        if ($globalOptions = Command::$globalOptions) {
            $cli .= ' [globals]';
        }
        $cli .= ' [command] [options]';
        $output->write("<fg=green>Usage: {$cli}</>".PHP_EOL);
        $output->write(PHP_EOL);
        if (count(Command::$globalOptions) > 0) {
            $output->write('<fg=green>Global Options:</>'.PHP_EOL);
            foreach (Command::$globalOptions as $option) {
                $output->write('  <fg=green>--'.$option['long'].'</>');
                if (null !== $option['short']) {
                    $output->write(', <fg=green>-'.$option['short'].'</>');
                }
                $output->write(' - '.$option['description'].PHP_EOL);
            }
            $output->write(PHP_EOL);
        }
        $output->write('<fg=green>Commands:</>'.PHP_EOL);
        $list = [];
        foreach ($this->commands as $module) {
            foreach ($module->getCommands() as $command) {
                $name = $command->getName();
                $list[$name] = '  '.$name.' - '.$command->getDescription();
            }
        }
        ksort(array: $list);
        $output->write(implode(PHP_EOL, $list).PHP_EOL);
    }

    public function getCommandModule(string $command): ?Module
    {
        return $this->commands[$command] ?? null;
    }

    public function handleException(\Throwable $e): void
    {
        $this->output->write(PHP_EOL);
        $this->output->write('<fg=red>Exception:</> <fg=white>'.get_class($e).'</>'.PHP_EOL);
        $this->output->write('<fg=red>File:</> <fg=white>'.$e->getFile().'</>'.PHP_EOL);
        $this->output->write('<fg=red>Line:</> <fg=white>'.$e->getLine().'</>'.PHP_EOL);
        $this->output->write('<fg=red>Error:</> <fg=white>'.$e->getMessage().'</>'.PHP_EOL);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $this->output->write(PHP_EOL);
        $this->output->write('<fg=red>Error:</> <fg=white>'.$errstr.'</>'.PHP_EOL);
        $this->output->write('<fg=red>File:</> <fg=white>'.$errfile.'</>'.PHP_EOL);
        $this->output->write('<fg=red>Line:</> <fg=white>'.$errline.'</>'.PHP_EOL);

        return true;
    }

    /**
     * Shutdown handler function.
     *
     * This function is responsible for executing the shutdown tasks registered in the global variable $__shutdownTasks.
     * It checks if the script is running in CLI mode or if headers have already been sent before executing the tasks.
     */
    public function shutdownHandler(): void
    {
        if (($error = error_get_last()) !== null) {
            echo 'FATAL ERROR'.PHP_EOL;
            echo 'Message: '.$error['message'].PHP_EOL;
            echo 'File: '.$error['file'].PHP_EOL;
            echo 'Line: '.$error['line'].PHP_EOL;

            exit($error['type']);
        }
    }
}
