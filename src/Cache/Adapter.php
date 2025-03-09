<?php

declare(strict_types=1);

/**
 * @file        Adapter.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://hazaar.io)
 *
 * @version     $Id: Application.php 24593 2012-08-29 20:35:02Z jamie $
 */

namespace Hazaar\Cache;

use Hazaar\Application;
use Hazaar\Util\Arr;

/**
 * The cache frontend.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class Adapter implements \ArrayAccess
{
    /**
     * @var array<mixed> The cache options
     */
    protected array $options;
    protected bool $useCache = true;
    protected Backend $backend;

    /**
     * Cache object constructor.
     *
     * @param array<string>|string $backend       The name of the backend to use. Currently 'apc', 'file', 'memcached', 'session' and
     *                                            'sqlite' are supported.
     * @param string               $namespace     The namespace to use for grouping stored data
     * @param array<mixed>         $configOptions
     */
    public function __construct(
        null|array|string $backend = null,
        array $configOptions = [],
        string $namespace = 'default'
    ) {
        $this->options = $configOptions;
        if (!$backend) {
            // Set up a default backend chain
            $backend = ['shm', 'session'];
            // Grab the application context so we can load any cache settings
            if (($app = Application::getInstance())
                && isset($app->config['cache']['backend'])) {
                $backend = $app->config['cache']['backend'];
                if (isset($app->config['cache']['options'])) {
                    $this->options = array_merge($this->options, $app->config['cache']['options']);
                }
            }
        }
        $this->configure([
            'lifetime' => 3600,
            'use_pragma' => true,
            'keepalive' => false,
        ]);
        if (!is_array($backend)) {
            $backend = [$backend];
        }
        foreach ($backend as $name) {
            $backendClass = '\Hazaar\Cache\Backend\\'.ucfirst($name);
            if (class_exists($backendClass) && $backendClass::available()) {
                break;
            }
            unset($backendClass);
        }
        if (!isset($backendClass)) {
            throw new Exception\NoBackendAvailable();
        }
        if (!in_array(Backend::class, class_parents($backendClass))) {
            throw new Exception\InvalidBackend($backendClass);
        }
        $this->backend = new $backendClass($this->options, $namespace);
        /*
         * Cache skip
         *
         * Check for a Pragma header to see if we should skip loading from cache.
         */
        if ($this->options['use_pragma'] && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (array_key_exists('Pragma', $headers) && 'no-cache' == $headers['Pragma']) {
                $this->useCache = false;
            }
        }
    }

    public function __destruct()
    {
        if (isset($this->backend)) {
            unset($this->backend);
        }
    }

    // MAGIC METHOD FOR DIRECT ACCESS
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __unset(string $key): void
    {
        $this->remove($key);
    }

    public function can(string $feature): bool
    {
        return $this->backend->can($feature);
    }

    /**
     * @param array<mixed> $options
     */
    public function configure(array $options): void
    {
        $this->options = Arr::enhance($this->options, $options);
    }

    public function getBackend(): Backend
    {
        return $this->backend;
    }

    public function setBackendOption(string $key, mixed $value): void
    {
        $this->backend->options = array_merge($this->backend->options, [$key => $value]);
    }

    public function lock(string $key): bool
    {
        return $this->backend->lock($key);
    }

    public function unlock(string $key): bool
    {
        return $this->backend->unlock($key);
    }

    /**
     * Retrieve a value from cache storage.
     *
     * @param string $key         The reference key used to store the value
     * @param mixed  $default     If the value doesn't exist, this default will be returned instead
     * @param bool   $saveDefault If the value doesn't exist and a default is specified, save that default to cache
     *
     * @return mixed the value that was stored in cache
     */
    public function &get(string $key, mixed $default = null, bool $saveDefault = false, int $timeout = 0): mixed
    {
        if (!$this->useCache) {
            return $default;
        }
        $result = $this->backend->get($key);
        if ($result && !$this->backend->can('store_objects')) {
            $result = unserialize($result);
        }
        if (null === $result) {
            if (true === $saveDefault) {
                $this->set($key, $default, $timeout);
            }

            return $default;
        }

        return $result;
    }

    /**
     * Store a value in the cache using the current cache backend.
     *
     * @param string $key     The reference key under which to store the value
     * @param mixed  $value   The value that should be stored. Values can be pretty much anything including integers, booleans,
     *                        strings and any object that can be serialised.
     * @param int    $timeout The number of seconds after which the value becomes invalid. If not set the global
     *                        'lifetime' option is used. Set a value of '-1' to indicate that the value should never timeout.
     */
    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        // If the backend can't store objects, serialize the value.
        if (!$this->backend->can('store_objects')) {
            $value = serialize($value);
        }

        return $this->backend->set($key, $value, $timeout);
    }

    /**
     * Check if a stored value exists.
     *
     * @param string $key        The value key to check for
     * @param bool   $checkEmpty Normally this method will return try if the value exists with `$key`.  Setting `$checkEmpty` looks at the value
     *                           and will return false if it is an 'empty' value (ie: 0, null, [])
     */
    public function has(string $key, bool $checkEmpty = false): bool
    {
        return $this->backend->has($key, $checkEmpty);
    }

    /**
     * Removes a stored value.
     *
     * @param string $key The key of the value to remove
     */
    public function remove(string $key): void
    {
        $this->backend->remove($key);
    }

    /**
     * Extend the cache with an array of key/value pairs.
     *
     * @param array<mixed> $array The array of key/value pairs to store in the cache
     */
    public function extend(array $array, bool $recursive = false): bool
    {
        foreach ($array as $key => $value) {
            if (is_array($value) && $recursive) {
                $c = $this->get($key);
                if ($c && is_array($c)) {
                    $value = array_merge_recursive($c, $value);
                }
                $this->set($key, $value);
            } else {
                $this->set($key, $value);
            }
        }

        return true;
    }

    public function clear(): void
    {
        $this->backend->clear();
    }

    /**
     * Set multiple values in the cache.
     *
     * @param array<mixed> $values
     */
    public function populate(array $values): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return true;
    }

    public function pull(string $key): mixed
    {
        $value = $this->get($key);
        $this->remove($key);

        return $value;
    }

    /**
     * Retrieve all values from the cache.
     *
     * @return array<mixed>
     */
    public function toArray(): ?array
    {
        if (!$this->backend->can('array')) {
            return null;
        }
        $values = $this->backend->toArray();
        if (!$this->backend->can('store_objects')) {
            foreach ($values as &$value) {
                $value = unserialize($value);
            }
        }

        return $values;
    }

    public function count(): int
    {
        return $this->backend->count();
    }

    /**
     * Disable the use of cache.
     *
     * This allows a cache object to be disabled but still be used without having to modify existing application
     * code.  The application can still call the get() method but it will always return false, simulating a negative
     * cache response.  Calling set() will still have an affect however.
     */
    public function on(): void
    {
        $this->useCache = true;
    }

    /**
     * Enable the use of cache.
     *
     * Cache is enabled by default.  This is to allow cache to be re-enabled after calling off().
     *
     * This method can also be used to force cache back on after being disabled by a "Pragma: no-cache" header
     * in the case where the use_pragma setting is enabled (which is the default).
     */
    public function off(): void
    {
        $this->useCache = false;
    }

    public function &__get(string $key): mixed
    {
        return $this->get($key);
    }

    // ARRAYACCESS METHODS
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * Increment key value.
     *
     * This method will increment a cached integer value by a defined amount (default is 1).  Once
     * the value is incremented it will be stored back in the cache and the new value returned.
     *
     * @param string $key    The cache key
     * @param int    $amount the amount to increment the value by
     *
     * @return int The new incremented value
     */
    public function increment(string $key, int $amount = 1): int
    {
        if (($value = $this->get($key)) === null || !is_int($value)) {
            $value = 0;
        }
        $this->set($key, $value += $amount);

        return $value;
    }

    /**
     * Decrement key value.
     *
     * This method will decrement a cached integer value by a defined amount (default is 1).  Once
     * the value is decremented it will be stored back in the cache and the new value returned.
     *
     * @param string $key    The cache key
     * @param int    $amount the amount to decrement the value by
     *
     * @return int The new decremented value
     */
    public function decrement(string $key, int $amount = 1): int
    {
        if (($value = $this->get($key)) === null || !is_int($value)) {
            $value = 0;
        }
        $this->set($key, $value -= $amount);

        return $value;
    }
}
