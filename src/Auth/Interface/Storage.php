<?php

declare(strict_types=1);

namespace Hazaar\Auth\Interface;

interface Storage
{
    /**
     * Construct the storage.
     *
     * @param array<mixed> $config The configuration
     */
    public function __construct(array $config);

    /**
     * Returns true if and only if storage is empty.
     */
    public function isEmpty(): bool;

    /**
     * Returns data from storage.
     *
     * @return array<string,mixed>
     */
    public function read(): array;

    /**
     * Writes data to storage.
     *
     * @param array<string,mixed> $data
     */
    public function write(array $data): void;

    /**
     * Checks if a key exists in storage.
     */
    public function has(string $key): bool;

    /**
     * Gets a value from storage.
     */
    public function get(string $key): mixed;

    /**
     * Sets a value in storage.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Unsets a value in storage.
     */
    public function unset(string $key): void;

    /**
     * Clears data from storage.
     */
    public function clear(): void;

    /**
     * Returns the storage session token.
     *
     * @return array<string,string> Storage token should be an array with at least a 'token' key
     *                              and optionally a 'refresh' key
     */
    public function getToken(): ?array;
}
