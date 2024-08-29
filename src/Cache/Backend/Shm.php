<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Shm.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Application;
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
    private ?\SysvSemaphore $sem;
    private ?\SysvSharedMemory $shm;
    private bool $keepalive;
    private int $indexKey;

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
        $app = Application::getInstance();
        $inodeFile = $app->runtimePath('.shm_inode'); // The inode file is used to create a unique key for the shared memory segment
        file_exists($inodeFile) || touch($inodeFile); // Create the inode file if it doesn't exist
        $addrIndex = ftok($inodeFile, chr(0));
        if (-1 === $addrIndex) {
            throw new \Exception('ftok() failed.');
        }
        $shmNSIndex = shm_attach($addrIndex, $this->options->get('ns_index.size', 10000), $this->options->get('ns_index.permissions', 0666));
        if (!$shmNSIndex instanceof \SysvSharedMemory) {
            throw new \Exception('shm_attach() failed.  did not return \SysvSharedMemory.');
        }
        $shmNSKey = crc32('cache_'.$namespace);
        // Create a semaphore to lock the namespace index
        $semNSKey = sem_get($shmNSKey, 1, 0666, true);
        if (!sem_acquire($semNSKey)) {
            throw new \Exception('Failed to acquire semaphore lock.');
        }
        if (shm_has_var($shmNSIndex, $shmNSKey)) {
            $NSIndex = shm_get_var($shmNSIndex, $shmNSKey);
        } else {
            $NSIndex = [];
        }
        if (!($NSkey = array_search($namespace, $NSIndex, true))) {
            $NSIndex[] = $namespace;
            $NSkey = array_search($namespace, $NSIndex, true) + 1;
            shm_put_var($shmNSIndex, $shmNSKey, $NSIndex);
        }
        $shmAddr = ftok($inodeFile, chr($NSkey));
        if (-1 === $shmAddr) {
            throw new \Exception('ftok() failed.');
        }
        // Release the semaphore
        sem_release($semNSKey);
        shm_detach($shmNSIndex);
        // Create a semaphore to lock the shared memory segment
        $this->sem = sem_get($shmAddr, 1, 0666, true);
        if (false === $this->sem) {
            throw new \Exception('sem_get() failed.');
        }
        // Attach to the shared memory segment
        $this->shm = shm_attach($shmAddr, $this->options->get('size', 1000000), $this->options->get('permissions', 0666));
        if (!$this->shm instanceof \SysvSharedMemory) {
            throw new \Exception('shm_attach() failed.  did not return \SysvSharedMemory.');
        }
        $this->indexKey = crc32('cache_'.$this->namespace.'_index');
    }

    /**
     * Closes the shared memory cache.
     *
     * @return bool returns true if the shared memory cache is successfully closed, false otherwise
     */
    public function close(): bool
    {
        if (null === $this->shm || null === $this->sem) {
            return false;
        }
        $index = $this->getIndex();
        if (!(array_key_exists(self::GC_KEY, $index)
            && $index[self::GC_KEY] > 0
            && (time() - $this->options->get('gc_interval', 10)) < $index[self::GC_KEY])) {
            $now = time();
            foreach ($index as $key => $i) {
                if (self::GC_KEY === $key) {
                    continue;
                }
                $result = $this->infoByAddr($i, true);
                if (false === $result) {
                    unset($index[$key]);
                }
            }
            $index[self::GC_KEY] = $now;
            shm_put_var($this->shm, $this->indexKey, $index);
        }
        shm_detach($this->shm);
        $this->shm = null;
        sem_remove($this->sem);
        $this->sem = null;
        // Release all locks
        foreach ($this->locks as $key => $lock) {
            sem_release($lock);
            unset($this->locks[$key]);
        }

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
        $removed = [];
        foreach ($index as $key => $addr) {
            if (self::GC_KEY === $key) {
                continue;
            }
            if ($info = $this->infoByAddr($addr)) {
                $array[$key] = $info['data'];
            } else {
                $removed[] = $key;
            }
        }
        if (count($removed) > 0) {
            $this->removeIndex($removed);
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
        $lock = sem_get($addr, 1, 0666, true);
        if (sem_acquire($lock)) {
            $this->locks[$addr] = $lock;

            return true;
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
        $addr = $this->getAddr($key, true);
        if (!array_key_exists($addr, $this->locks)) {
            return false;
        }
        if (!sem_release($this->locks[$addr])) {
            return false;
        }
        unset($this->locks[$addr]);

        return true;
    }

    /**
     * Retrieves the index from the shared memory.
     *
     * @return array<string,int> The index array retrieved from the shared memory. If the shared memory does not have any variables, an empty array is returned.
     */
    private function getIndex(): array
    {
        return shm_has_var($this->shm, $this->indexKey) ? shm_get_var($this->shm, $this->indexKey) : [];
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
        $index[$key] = crc32('cache_'.$this->namespace.'_'.$key);
        shm_put_var($this->shm, $this->indexKey, $index);
        sem_release($this->sem);

        return $index[$key];
    }

    /**
     * Remove an entry from the cache index.
     *
     * @param array<string>|string $key the key of the entry to be removed
     *
     * @return bool returns true if the entry was successfully removed, false otherwise
     */
    private function removeIndex(array|string $key): bool
    {
        if (!sem_acquire($this->sem)) {
            return false;
        }
        $index = $this->getIndex();
        if (is_array($key)) {
            foreach ($key as $k) {
                if (array_key_exists($k, $index)) {
                    unset($index[$k]);
                }
            }
        } elseif (array_key_exists($key, $index)) {
            unset($index[$key]);
        }
        shm_put_var($this->shm, $this->indexKey, $index);
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
            if ($info['expire'] <= time()) {
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
