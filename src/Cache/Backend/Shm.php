<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Shm.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Cache\Backend;

/**
 * @brief The PHP-SHM (shared memory) cache backend.
 *
 * @detail This is the absolute fastest caching backend.
 *
 * There are no configuration options required for the backend.
 */
class Shm extends Backend
{
    protected int $weight = 0;
    private ?\SysvSharedMemory $shm_index;
    private ?\SysvSharedMemory $shm;

    /**
     * @var array<mixed>
     */
    private array $index = [];

    public static function available(): bool
    {
        return function_exists('shm_attach');
    }

    public function init(string $namespace): void
    {
        $this->addCapabilities('store_objects', 'keepalive', 'array');
        $this->shm_index = shm_attach(1);
        if (shm_has_var($this->shm_index, 0)) {
            $namespaces = shm_get_var($this->shm_index, 0);
        } else {
            $namespaces = [0 => 'index'];
            shm_put_var($this->shm_index, 0, $namespaces);
        }
        if (!($key = array_search($namespace, $namespaces))) {
            $key = count($namespaces) + 1;
            $namespaces[$key] = $namespace;
            shm_put_var($this->shm_index, 0, $namespaces);
        }
        $this->shm = shm_attach($key);
        if (!$this->shm instanceof \SysvSharedMemory) {
            throw new \Exception('shm_attach() failed.  did not return \SysvSharedMemory.');
        }
        if (shm_has_var($this->shm, 0)) {
            $this->index = shm_get_var($this->shm, 0);
        }
    }

    public function close(): bool
    {
        if (null === $this->shm) {
            return false;
        }

        return shm_detach($this->shm);
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        $key = $this->namespace.'::'.$key;
        if (!($index = array_search($key, $this->index))) {
            return false;
        }
        if (!shm_has_var($this->shm, $index)) {
            return false;
        }
        $info = shm_get_var($this->shm, $index);
        if (array_key_exists('expire', $info) && $info['expire'] < time()) {
            return false;
        }

        return true === $check_empty ? empty($info['data']) : true;
    }

    public function get(string $key): mixed
    {
        $key = $this->namespace.'::'.$key;
        if (!($index = array_search($key, $this->index))) {
            return false;
        }
        $info = $this->info($index);

        return $info['data'];
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        $key = $this->namespace.'::'.$key;
        if (!($index = array_search($key, $this->index))) {
            $index = count($this->index) + 1; // Plus one because we can't use zero as it's our index.
            $this->index[$index] = $key;
            shm_put_var($this->shm, 0, $this->index);
        }
        $info = ['data' => $value];
        if ($timeout > 0) {
            $info['timeout'] = $timeout;
            $info['expire'] = time() + $timeout;
        }

        return shm_put_var($this->shm, $index, $info);
    }

    public function remove(string $key): bool
    {
        $key = $this->namespace.'::'.$key;
        if (!($index = array_search($key, $this->index))) {
            return false;
        }
        unset($this->index[$index]);
        shm_put_var($this->shm, 0, $this->index);

        return shm_remove_var($this->shm, $index);
    }

    public function clear(): bool
    {
        if (shm_remove($this->shm)) {
            $this->index = [];

            return true;
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $array = [];
        foreach ($this->index as $index => $key) {
            if ($info = $this->info($index)) {
                $array[$key] = $info['data'];
            }
        }

        return $array;
    }

    /**
     * @return array<mixed>|bool
     */
    private function info(int $index): array|bool
    {
        $info = shm_get_var($this->shm, $index);
        if (array_key_exists('expire', $info)) {
            if ($info['expire'] < time()) {
                return false;
            }
            $info['expire'] = time() + $info['timeout'];
            shm_put_var($this->shm, $index, $info);
        }

        return $info;
    }
}
