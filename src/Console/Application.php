<?php

declare(strict_types=1);

namespace Hazaar\Console;

class Application
{
    private string $name;
    private string $version;

    /** @var array<Command> */
    private array $commands = [];

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
        $this->add(new HelpCommand());
    }

    public function add(Command $command): void
    {
        $command->initialise($this->input, $this->output);
        $this->commands[$command->getName()] = $command;
    }

    public function run(): int
    {
        $this->output->write('<fg=green>'.$this->name.' v'.$this->version.'</>'.PHP_EOL);
        $commandName = $this->input->getCommand();
        if (!array_key_exists($commandName, $this->commands)) {
            $this->writeHelp($this->output);

            return 1;
        }
        $command = $this->commands[$commandName];
        $this->input->initialise($command);
        $code = $command->run($this);
        if (-1 === $code) {
            $this->writeHelp($this->output);
            $code = 1;
        }

        return $code;
    }

    public function writeHelp(Output $output): void
    {
        $output->write('<fg=green>Usage: '.$this->name.' [command] [options]</>'.PHP_EOL);
        $output->write(PHP_EOL);
        $output->write('<fg=green>Commands:</>'.PHP_EOL);
        foreach ($this->commands as $command) {
            $output->write('  '.$command->getName().' - '.$command->getDescription().PHP_EOL);
        }
    }

    public function getCommandObject(string $command): ?Command
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
}
