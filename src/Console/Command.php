<?php

declare(strict_types=1);

namespace Hazaar\Console;

class Command
{
    /**
     * @var array<array{long: string, short: null|string, description: null|string, required: bool}>
     */
    public static array $globalOptions = [];
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

    private mixed $callback;

    /**
     * @param array{object,string} $callback
     */
    final public function __construct(string $name, array $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * @return array{object,string}
     */
    public function getCallable(): array
    {
        return $this->callback;
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

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function setHelp(string $help): self
    {
        return $this;
    }

    public function addOption(
        string $long,
        ?string $short = null,
        ?string $description = null,
        bool $required = false
    ): self {
        $this->options[] = [
            'long' => $long,
            'short' => $short,
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }

    public function addArgument(
        string $name,
        ?string $description = null,
        bool $required = false
    ): self {
        $this->arguments[] = [
            'name' => $name,
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }

    public function addGlobalOption(
        string $long,
        ?string $short = null,
        ?string $description = null,
        bool $required = false
    ): self {
        self::$globalOptions[] = [
            'long' => $long,
            'short' => $short,
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }
}
