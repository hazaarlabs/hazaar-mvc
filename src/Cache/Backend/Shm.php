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
    private ?\SysvSharedMemory $shm;
    private ?\SysvSemaphore $sem;

    /**
     * @var array<string,\SysvSemaphore>
     */
    private array $locks = [];

    public static function available(): bool
    {
        return function_exists('shm_attach') && function_exists('sem_get');
    }

    public function init(string $namespace): void
    {
        $this->addCapabilities('store_objects', 'keepalive', 'array', 'lock');
        $shmNamespaceAddr = ftok(__FILE__, chr(0));
        $shmNamespaceIndex = shm_attach($shmNamespaceAddr);
        if (!$shmNamespaceIndex instanceof \SysvSharedMemory) {
            throw new \Exception('shm_attach() failed.  did not return \SysvSharedMemory.');
        }
        // Create a semaphore to lock the namespace index
        $this->sem = sem_get($shmNamespaceAddr, 1, 0666, true);
        if (!sem_acquire($this->sem)) {
            throw new \Exception('Failed to acquire semaphore lock.');
        }
        if (shm_has_var($shmNamespaceIndex, 0)) {
            $namespaces = shm_get_var($shmNamespaceIndex, 0);
        }
        if (!(isset($namespaces) && is_array($namespaces))) {
            $namespaces = [];
        }
        if (!($key = array_key_exists($namespace, $namespaces))) {
            $namespaces[$namespace] = ftok(__FILE__, chr(count($namespaces) + 1)); // Plus one because we can't use zero as it's our index.
            shm_put_var($shmNamespaceIndex, 0, $namespaces);
        }
        // Release the semaphore
        sem_release($this->sem);
        shm_detach($shmNamespaceIndex);
        $this->shm = shm_attach($namespaces[$namespace]);
        if (!$this->shm instanceof \SysvSharedMemory) {
            throw new \Exception('shm_attach() failed.  did not return \SysvSharedMemory.');
        }
    }

    public function close(): bool
    {
        if (null === $this->shm || !isset($this->shmNamespaceAddr)) {
            return false;
        }
        if (!sem_acquire($this->sem)) {
            return false;
        }
        $index = $this->getIndex();
        foreach ($index as $key => $i) {
            // TODO: Remove expired items
        }
        shm_put_var($this->shm, 0, $index);
        shm_detach($this->shm);
        sem_release($this->sem);
        $this->shm = null;

        return true;
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        $index = $this->getIndex();
        if (!array_key_exists($key, $index)) {
            return false;
        }
        if (!shm_has_var($this->shm, $index[$key])) {
            return false;
        }
        $info = $this->infoByAddr($index[$key]);
        if (false === $info) {
            return false;
        }

        return true === $check_empty ? empty($info['data']) : true;
    }

    public function get(string $key): mixed
    {
        $info = $this->infoByKey($key);
        if (false === $info) {
            return false;
        }

        return $info['data'];
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        $addr = $this->getAddr($key);
        $info = ['data' => $value];
        if ($timeout > 0) {
            $info['timeout'] = $timeout;
            $info['expire'] = time() + $timeout;
        }

        return shm_put_var($this->shm, $addr, $info);
    }

    public function remove(string $key): bool
    {
        $addr = $this->getAddr($key);
        if (false === $addr) {
            return false;
        }
        $this->removeIndex($key);

        return shm_remove_var($this->shm, $addr);
    }

    public function clear(): bool
    {
        if (shm_remove($this->shm)) {
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
        $index = $this->getIndex();
        foreach ($index as $key => $addr) {
            if ($info = $this->infoByAddr($addr)) {
                $array[$key] = $info['data'];
            }
        }

        return $array;
    }

    public function count(): int
    {
        $index = $this->getIndex();

        return count($index);
    }

    public function lock(string $key): bool
    {
        $addr = $this->getAddr($key, true);
        $this->locks[$key] = sem_get($addr, 1, 0666, true);
        if (!sem_acquire($this->locks[$key])) {
            unset($this->locks[$key]);

            return false;
        }

        return true;
    }

    public function unlock(string $key): bool
    {
        if (!array_key_exists($key, $this->locks)) {
            return false;
        }
        if (!sem_release($this->locks[$key])) {
            return false;
        }
        unset($this->locks[$key]);

        return true;
    }

    /**
     * @return array<string,int>
     */
    private function getIndex(): array
    {
        return shm_has_var($this->shm, 0) ? shm_get_var($this->shm, 0) : [];
    }

    private function getAddr(string $key, bool $create = false): false|int
    {
        $index = $this->getIndex();
        if (!array_key_exists($key, $index)) {
            if (!$create) {
                return false;
            }

            return $this->addIndex($key);
        }

        return $index[$key];
    }

    private function addIndex(string $key): false|int
    {
        if (!sem_acquire($this->sem)) {
            return false;
        }
        $index = $this->getIndex();
        // TODO: Find a better way to get the next available address
        $index[$key] = count($index) + 1;
        shm_put_var($this->shm, 0, $index);
        sem_release($this->sem);

        return $index[$key];
    }

    private function removeIndex(string $key): bool
    {
        if (!sem_acquire($this->sem)) {
            return false;
        }
        $index = $this->getIndex();
        if (array_key_exists($key, $index)) {
            unset($index[$key]);
            shm_put_var($this->shm, 0, $index);
        }
        sem_release($this->sem);

        return true;
    }

    /**
     * @return array<mixed>|bool
     */
    private function infoByAddr(int $addr): array|bool
    {
        if (!shm_has_var($this->shm, $addr)) {
            return false;
        }
        $info = @shm_get_var($this->shm, $addr);
        if (false === $info) {
            return false;
        }
        if (array_key_exists('expire', $info)) {
            if ($info['expire'] < time()) {
                return false;
            }
            // Keepalive
            $info['expire'] = time() + $info['timeout'];
            shm_put_var($this->shm, $addr, $info);
        }

        return $info;
    }

    /**
     * @return array<mixed>|bool
     */
    private function infoByKey(string $key): array|bool
    {
        $index = $this->getIndex();
        if (!array_key_exists($key, $index)) {
            return false;
        }

        return $this->infoByAddr($index[$key]);
    }
}
