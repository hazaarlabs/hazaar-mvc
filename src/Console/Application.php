<?php

declare(strict_types=1);

namespace Hazaar\Console;

use Hazaar\Application\Runtime;
use Hazaar\Console\Modules\HelpModule;

class Application
{
    /**
     * @var array<Module>
     */
    public array $modules = [];

    /**
     * A list of commands registered with the application by modules.
     *
     * @var array<string,array<Module>>
     */
    public array $commands = [];
    private string $name;
    private string $version;

    private Input $input;
    private Output $output;

    /**
     * A list of methods registered with the application.
     *
     * @var array{Module,string}
     */
    private array $methods = [];

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
        Runtime::createInstance(__DIR__.'/../../');
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
            $moduleName = $module->getName() ?? 'default';
            $this->commands[$moduleName] = array_merge($this->commands[$moduleName] ?? [], array_combine(array_keys($module->getCommands()), $modules));
        }
        $result = $this->input->initialise((array) $_SERVER['argv'], array_keys($this->commands));
        if (false === $result) {
            $this->output->write('<fg=red>Invalid command line arguments</>'.PHP_EOL);
            $this->output->write('<fg=yellow>Use "'.$this->input->getExecutable().' help" for usage information</>'.PHP_EOL);

            return 1;
        }
        $this->output->write('<fg=green>'.$this->name.' v'.$this->version.'</>'.PHP_EOL);
        define('APPLICATION_ENV', $this->input->getGlobalOption('env') ?? getenv('APPLICATION_ENV') ?: 'development');
        $this->output->write('<fg=green>Environment: '.APPLICATION_ENV.'</>'.PHP_EOL.PHP_EOL);
        $moduleName = $this->input->getModule();
        if (!array_key_exists($moduleName, $this->commands)) {
            $moduleName = 'default';
        }
        $commandName = $this->input->getCommand();
        if (!($commandName && array_key_exists($commandName, $this->commands[$moduleName]))) {
            $helpModule = new HelpModule();
            $helpModule->initialise($this, $this->input, $this->output);

            return $helpModule->execute($this->input, $this->output);
        }
        $module = $this->commands[$moduleName][$commandName];
        foreach ($this->methods as $method) {
            call_user_func($method, $this->input, $this->output);
        }

        return $module->run($commandName);
    }

    /**
     * @param array{Module,string} $method
     */
    public function registerMethod(array $method): void
    {
        $this->methods[] = $method;
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
