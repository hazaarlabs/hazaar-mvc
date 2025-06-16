<?php

declare(strict_types=1);

namespace Hazaar\Console;

abstract class Module
{
    protected Application $application;
    private string $name;
    private string $description;

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

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name ?? null;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description ?? null;
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
            $result = $this->prepare($this->input, $this->output);
            if ($result > 0) {
                return $result;
            }
            $callable = $command->getCallable();
            $result = $callable($this->input, $this->output);
        } catch (\Exception $e) {
            $this->output->write($e->getMessage().PHP_EOL);
        }

        return $result;
    }

    public function addGlobalOption(
        string $long,
        ?string $short = null,
        ?string $description = null,
        ?bool $takesValue = null,
        mixed $default = null,
        ?string $valueType = null
    ): self {
        Command::$globalOptions[$long] = new Option(
            long: $long,
            short: $short,
            description: $description,
            takesValue: null === $takesValue ? null !== $valueType : false,
            default: $default,
            valueType: $valueType
        );

        return $this;
    }

    protected function prepare(Input $input, Output $output): int
    {
        return 0;
    }

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
