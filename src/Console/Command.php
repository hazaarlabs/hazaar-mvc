<?php

declare(strict_types=1);

namespace Hazaar\Console;

class Command
{
    /**
     * @var array<Option>
     */
    public static array $globalOptions = [];
    protected Application $application;
    private string $name;
    private string $description;

    /**
     * @var array<string,Option>
     */
    private array $options = [];

    /**
     * @var array<Argument>
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
     * @return array<Option>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array<Argument>
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
        ?bool $takesValue = null,
        mixed $default = null,
        ?string $valueType = null
    ): self {
        $this->options[$long] = new Option(
            long: $long,
            short: $short,
            description: $description,
            takesValue: null === $takesValue ? null !== $valueType : false,
            default: $default,
            valueType: $valueType
        );

        return $this;
    }

    public function addArgument(
        string $name,
        ?string $description = null,
        bool $required = false
    ): self {
        $this->arguments[] = new Argument(
            name: $name,
            description: $description,
            required: $required
        );

        return $this;
    }

    public function addGlobalOption(
        string $long,
        ?string $short = null,
        ?string $description = null,
        bool $takesValue = false,
        mixed $default = null,
        ?string $valueType = null
    ): self {
        self::$globalOptions[$long] = new Option(
            long: $long,
            short: $short,
            description: $description,
            takesValue: $takesValue,
            default: $default,
            valueType: $valueType
        );

        return $this;
    }

    public function findOption(string $name): ?Option
    {
        foreach ($this->options as $option) {
            if ($option->long === $name || $option->short === $name) {
                return $option;
            }
        }

        return null;
    }
}
