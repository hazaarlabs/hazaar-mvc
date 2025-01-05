<?php

declare(strict_types=1);

namespace Hazaar\Console;

abstract class Command
{
    protected Application $application;
    private string $name;
    private string $description;

    /**
     * @var array<array{long: string, short: null|string, description: null|string, required: bool}>
     */
    private array $options = [];

    /**
     * @var array<array{name: string, description: string, required: bool}>
     */
    private array $arguments = [];

    private Input $input;
    private Output $output;

    public function initialise(Input $input, Output $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->configure();
    }

    public function run(Application $application): int
    {
        $this->application = $application;
        $this->prepare($this->input, $this->output);

        return $this->execute($this->input, $this->output);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<array{long: string, short: null|string, description: null|string, required: bool}>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<array{name: string, description: string, required: bool}>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    protected function configure(): void {}

    protected function prepare(Input $input, Output $output): void {}

    protected function execute(Input $input, Output $output): int
    {
        return 0;
    }

    protected function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    protected function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    protected function setHelp(string $help): self
    {
        return $this;
    }

    protected function addOption(string $long, ?string $short, ?string $description, bool $required = false): self
    {
        $this->options[] = [
            'long' => $long,
            'short' => $short,
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }

    protected function addArgument(string $name, string $description, bool $required = false): self
    {
        $this->arguments[] = [
            'name' => $name,
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }
}
