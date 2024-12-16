<?php

declare(strict_types=1);

namespace Hazaar\Logger;

abstract class Backend implements Interfaces\Backend
{
    protected const LOG_LEVEL_PREFIX = 'LOG_';

    /**
     * @var array<int>
     */
    protected array $levels;

    /**
     * @var array<mixed>
     */
    private array $options;

    /**
     * @var array<string>
     */
    private array $capabilities = [];

    /**
     * @param array<mixed> $options
     */
    public function __construct(array $options)
    {
        $this->levels = array_filter(get_defined_constants(), function ($value) {
            return self::LOG_LEVEL_PREFIX === substr($value, 0, strlen(self::LOG_LEVEL_PREFIX));
        }, ARRAY_FILTER_USE_KEY);
        // Set the options we were given which will overwrite any defaults
        $this->options = $options;
        $this->init();
    }

    public function init(): void {}

    public function postRun(): void
    {
        // do nothing
    }

    public function setDefaultOption(string $key, mixed $value): void
    {
        if (!isset($this->options[$key])) {
            $this->setOption($key, $value);
        }
    }

    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    public function getOption(string $key): mixed
    {
        if (!isset($this->options[$key])) {
            return null;
        }

        return $this->options[$key];
    }

    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    public function getLogLevelId(string $level): int
    {
        $level = strtoupper($level);
        if (self::LOG_LEVEL_PREFIX !== substr($level, 0, strlen(self::LOG_LEVEL_PREFIX))) {
            $level = self::LOG_LEVEL_PREFIX.$level;
        }

        return defined($level) ? constant($level) : 0;
    }

    public function getLogLevelName(int $level): string
    {
        return substr(array_search($level, $this->levels), strlen(self::LOG_LEVEL_PREFIX));
    }

    /**
     * @return array<string>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function can(string $capability): bool
    {
        return in_array($capability, $this->capabilities);
    }

    protected function addCapability(string $capability): void
    {
        $this->capabilities[] = $capability;
    }
}
