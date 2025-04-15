<?php

declare(strict_types=1);

namespace Hazaar\Application;

class Runtime
{
    /**
     * @var array<string, Runtime>
     */
    private static array $instances = [];
    private string $path;

    private function __construct(string $path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException('Path does not exist: '.$path);
        }

        if (!is_readable($path)) {
            throw new \RuntimeException('Path is not readable: '.$path);
        }

        if (!is_writable($path)) {
            throw new \RuntimeException('Path is not writable: '.$path);
        }
        $this->path = realpath($path);
    }

    public static function getInstance(string $name = 'hazaar'): self
    {
        if (!isset(self::$instances[$name])) {
            throw new \RuntimeException('Runtime instance not found: '.$name);
        }

        return self::$instances[$name];
    }

    public static function createInstance(string $path, string $name = 'hazaar'): self
    {
        if (isset(self::$instances[$name])) {
            throw new \RuntimeException('Instance already exists: '.$name);
        }
        if (!is_dir($path)) {
            throw new \InvalidArgumentException('Path does not exist: '.$path);
        }
        self::$instances[$name] = new self($path);

        return self::$instances[$name];
    }

    public function getPath(?string $pathSuffix = null): string
    {
        if (null === $pathSuffix) {
            return $this->path;
        }

        return $this->path.($pathSuffix ? DIRECTORY_SEPARATOR.$pathSuffix : '');
    }
}
