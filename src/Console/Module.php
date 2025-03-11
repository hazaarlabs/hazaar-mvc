<?php

declare(strict_types=1);

namespace Hazaar\Console;

abstract class Module
{
    protected Application $application;

    /**
     * @var array<Command>
     */
    private array $commands = [];

    private Input $input;
    private Output $output;

    final public function initialise(Application $application, Input $input, Output $output): void
    {
        $this->application = $application;
        $this->input = $input;
        $this->output = $output;
        $this->configure();
    }

    /**
     * @return array<string,Command>
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getCommand(string $name): ?Command
    {
        return $this->commands[$name] ?? null;
    }

    public function run(string $command): int
    {
        $result = 1;

        try {
            if (!isset($this->commands[$command])) {
                throw new \Exception('Command not found', 1);
            }
            $command = $this->commands[$command];
            $this->input->run($command);
            $this->prepare($this->input, $this->output);
            $callable = $command->getCallable();
            $result = $callable($this->input, $this->output);
        } catch (\Exception $e) {
            $this->output->write($e->getMessage().PHP_EOL);
        }

        return $result;
    }

    protected function prepare(Input $input, Output $output): void {}

    /**
     * @param array{object,string} $callback
     */
    protected function addCommand(string $name, ?array $callback = null): Command
    {
        if (null === $callback) {
            $callback = [$this, 'execute'];
        }

        return $this->commands[$name] = new Command($name, $callback);
    }

    protected function configure(): void {}
}
