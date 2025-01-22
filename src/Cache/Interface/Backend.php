<?php

declare(strict_types=1);

namespace Hazaar\Cache\Interface;

interface Backend
{
    public function init(string $namespace): void;

    public function has(string $key, bool $check_empty = false): bool;

    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $timeout = 0): bool;

    public function remove(string $key): bool;

    public function clear(): bool;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;

    public function count(): int;

    public function setOption(string $key, mixed $value): void;
}
