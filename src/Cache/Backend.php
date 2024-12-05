<?php

namespace Hazaar\Cache;

use Hazaar\Map;

abstract class Backend implements Interfaces\Backend
{
    /**
     * The backend options.
     *
     * @var array<mixed>
     */
    public array $options;
    protected int $weight = 10;
    protected ?string $namespace;

    /**
     * The backends list of capabilities.
     *
     * Standard capabilities are:
     * * store_objects - Backend can directly store & return objects.  Whether the backend itself really can (like APCu) or the backend class takes care of this.
     * * compress - Can compress objects being stored.  If the backend can do this, then we don't want the frontend to ever do it.
     * * array - Can return all elements in the cache as an associative array
     * * expire_ns - Backend supports storage TTLs on the namespace as a whole.
     * * expire_val - Backend supports storage TTLs on individual values stored within the namespace
     * * keepalive - When a value is accessed it's TTL can be reset to keep it alive in cache.
     *
     * @var array<string>
     */
    private array $capabilities = [];

    /**
     * Backend constructor.
     *
     * @param array<mixed> $options
     */
    final public function __construct(array $options, string $namespace)
    {
        $this->options = $options;
        // Initialise the frontend.  This allows the frontend to return some default options.
        $this->namespace = $namespace;
        $this->init($namespace);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): bool
    {
        return true;
    }

    public function can(string $key): bool
    {
        return in_array($key, $this->capabilities);
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setOption(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }

    public function lock(string $key): bool
    {
        return false;
    }

    public function unlock(string $key): bool
    {
        return false;
    }

    protected function addCapabilities(string ...$args): void
    {
        foreach ($args as $arg) {
            if (!in_array($arg, $this->capabilities)) {
                $this->capabilities[] = $arg;
            }
        }
    }

    /**
     * @param array<mixed> $options
     */
    protected function configure(array $options): void
    {
        $this->options = array_enhance($this->options, $options);
    }
}
