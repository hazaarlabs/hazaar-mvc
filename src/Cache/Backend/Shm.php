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
    private const GC_KEY = '__garbage_collection__';
    protected int $weight = 0;
    private ?\SysvSharedMemory $shm;
    private ?\SysvSemaphore $sem;
    private bool $keepalive;

    /**
     * @var array<string,\SysvSemaphore>
     */
    private array $locks = [];

    /**
     * Check if the shared memory cache backend is available.
     *
     * @return bool returns true if the shared memory cache backend is available, false otherwise
     */
    public static function available(): bool
    {
        return function_exists('shm_attach') && function_exists('sem_get');
    }

    /**
     * Initializes the shared memory cache backend for a given namespace.
     *
     * @param string $namespace the namespace for the cache backend
     *
     * @throws \Exception if the shared memory attachment or semaphore acquisition fails
     */
    public function init(string $namespace): void
    {
        $this->addCapabilities('store_objects', 'keepalive', 'array', 'lock');
        $this->keepalive = $this->options['keepalive'];
        $shmNamespaceAddr = ftok(__FILE__, chr(0));
        $shmNamespaceIndex = shm_attach($shmNamespaceAddr, $this->options->get('index.size', 1000000), $this->options->get('index.permissions', 0666));
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
        $this->shm = shm_attach($namespaces[$namespace], $this->options->get('size', 1000000), $this->options->get('permissions', 0666));
        if (!$this->shm instanceof \SysvSharedMemory) {
            throw new \Exception('shm_attach() failed.  did not return \SysvSharedMemory.');
        }
    }

    /**
     * Closes the shared memory cache.
     *
     * @return bool returns true if the shared memory cache is successfully closed, false otherwise
     */
    public function close(): bool
    {
        if (null === $this->shm) {
            return false;
        }
        if (!sem_acquire($this->sem)) {
            return false;
        }
        $index = $this->getIndex();
        if (!(array_key_exists(self::GC_KEY, $index)
            && $index[self::GC_KEY] > 0
            && (time() - $this->options->get('gc_interval', 300)) < $index[self::GC_KEY])) {
            $now = time();
            foreach ($index as $key => $i) {
                if ('__' === substr($key, 0, 2)) {
                    continue;
                }
                $result = $this->infoByAddr($i, true);
                if (false === $result) {
                    unset($index[$key]);
                }
            }
            $index[self::GC_KEY] = $now;
            shm_put_var($this->shm, 0, $index);
        }
        shm_detach($this->shm);
        sem_release($this->sem);
        $this->shm = null;

        return true;
    }

    /**
     * Check if a key exists in the shared memory cache.
     *
     * @param string $key         the key to check
     * @param bool   $check_empty whether to check if the data associated with the key is empty
     *
     * @return bool returns true if the key exists in the cache, false otherwise
     */
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

    /**
     * Retrieves the value associated with the given key from the cache.
     *
     * @param string $key the key to retrieve the value for
     *
     * @return false|mixed the value associated with the key, or false if the key does not exist
     */
    public function get(string $key): mixed
    {
        $info = $this->infoByKey($key);
        if (false === $info) {
            return false;
        }

        return $info['data'];
    }

    /**
     * Sets a value in the shared memory cache.
     *
     * @param string $key     the key to store the value under
     * @param mixed  $value   the value to be stored
     * @param int    $timeout The timeout for the value in seconds. Default is 0 (no timeout).
     *
     * @return bool returns true on success, false on failure
     */
    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        $addr = $this->getAddr($key, true);
        $info = ['data' => $value];
        if ($timeout > 0) {
            $info['timeout'] = $timeout;
            $info['expire'] = time() + $timeout;
        }

        return shm_put_var($this->shm, $addr, $info);
    }

    /**
     * Remove a value from the shared memory cache.
     *
     * @param string $key the key of the value to remove
     *
     * @return bool returns true if the value was successfully removed, false otherwise
     */
    public function remove(string $key): bool
    {
        $addr = $this->getAddr($key);
        if (false === $addr) {
            return false;
        }
        $this->removeIndex($key);

        return shm_remove_var($this->shm, $addr);
    }

    /**
     * Clears the shared memory cache.
     *
     * @return bool returns true if the shared memory cache was successfully cleared, false otherwise
     */
    public function clear(): bool
    {
        if (shm_remove($this->shm)) {
            return true;
        }

        return false;
    }

    /**
     * Converts the cache data to an array.
     *
     * @return array<string,mixed> the cache data as an array
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

    /**
     * Returns the number of items in the cache.
     *
     * @return int the number of items in the cache
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * Locks a cache entry for exclusive access.
     *
     * @param string $key the key of the cache entry
     *
     * @return bool returns true if the cache entry was successfully locked, false otherwise
     */
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

    /**
     * Unlock a lock for a given key.
     *
     * @param string $key the key of the lock to unlock
     *
     * @return bool returns true if the lock was successfully unlocked, false otherwise
     */
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
     * Retrieves the index from the shared memory.
     *
     * @return array<string,int> The index array retrieved from the shared memory. If the shared memory does not have any variables, an empty array is returned.
     */
    private function getIndex(): array
    {
        return shm_has_var($this->shm, 0) ? shm_get_var($this->shm, 0) : [];
    }

    /**
     * Retrieves the address of a key in the cache.
     *
     * @param string $key    the key to retrieve the address for
     * @param bool   $create whether to create the key if it doesn't exist
     *
     * @return false|int the address of the key if it exists, false if it doesn't exist and $create is false
     */
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

    /**
     * Adds an index for the given key in the shared memory cache backend.
     *
     * @param string $key the key to add an index for
     *
     * @return false|int the index of the added key, or false if acquiring the semaphore fails
     */
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

    /**
     * Remove an entry from the cache index.
     *
     * @param string $key the key of the entry to be removed
     *
     * @return bool returns true if the entry was successfully removed, false otherwise
     */
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
     * Retrieves information stored in shared memory by address.
     *
     * @param int $addr the address of the shared memory variable
     *
     * @return array<mixed>|bool the information stored in shared memory if it exists and has not expired, otherwise false
     */
    private function infoByAddr(int $addr, bool $noKeepalive = false): array|bool
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
                shm_remove_var($this->shm, $addr);

                return false;
            }
            // Keepalive
            if (true === $this->keepalive && false === $noKeepalive) {
                $info['expire'] = time() + $info['timeout'];
                shm_put_var($this->shm, $addr, $info);
            }
        }

        return $info;
    }

    /**
     * Retrieves information about a cache entry by its key.
     *
     * @param string $key the key of the cache entry
     *
     * @return array<mixed>|bool returns an array containing information about the cache entry if it exists, or false otherwise
     */
    private function infoByKey(string $key): array|bool
    {
        $index = $this->getIndex();
        if (!array_key_exists($key, $index)) {
            return false;
        }
        $value = $this->infoByAddr($index[$key]);
        if (false === $value) {
            $this->removeIndex($key);
        }

        return $value;
    }
}
