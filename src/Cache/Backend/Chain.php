<?php
/**
 * @file        Hazaar/Cache/Backend/Chain.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Cache\Backend;
use Hazaar\Exception;

/**
 * @brief The cache backend chaining backend.
 *
 * @detail This backend allows other caching backends to be chained together for fault tollerance.  All operations (has,get,set,remove) are
 * performed on ALL backends at once.
 *
 * Configuration options for this backend is an array of 'backend_name' => 'backend_options' pairs.  (see each backend for available options)
 *
 * For example:
 *
 * [ 'file' => [ 'cache_dir' => '/tmp/cache' ], 'memcached' => [ 'server' => 'localhost' ] ]
 */
class Chain extends Backend
{
    /**
     * @var array<Backend>
     */
    private array $backends = [];

    /**
     * @var array<string>
     */
    private array $order = [];

    public static function available(): bool
    {
        return true;
    }

    public function init(string $namespace): void
    {
        if (!$this->options->has('backends')) {
            throw new Exception('Chain cache backend requires a "backends" option to be set!');
        }
        foreach ($this->options['backends'] as $backend => $backendOptions) {
            $backend = strtolower($backend);
            $backendClass = '\\Hazaar\\Cache\\Backend\\'.ucfirst($backend);
            if (!(class_exists($backendClass) && $backendClass::available())) {
                continue;
            }
            $obj = new $backendClass($backendOptions, $namespace);
            $this->backends[$backend] = $obj;
            $this->order[$backend] = $obj->getWeight();
        }
        asort($this->order);
        $this->addCapabilities('store_objects');
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        foreach ($this->backends as $backend) {
            if ($backend->has($key, $check_empty)) {
                return true;
            }
        }

        return false;
    }

    public function get(string $key): mixed
    {
        $store = [];
        $value = false;
        foreach ($this->order as $backend => $weight) {
            if (($value = $this->backends[$backend]->get($key)) === false) {
                $store[] = $backend;
            } else {
                break;
            }
        }
        if (false !== $value) {
            foreach ($store as $backend) {
                $this->backends[$backend]->set($key, $value);
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        foreach ($this->backends as $backend) {
            if (false === $backend->set($key, $value, $timeout)) {
                return false;
            }
        }

        return true;
    }

    public function remove(string $key): bool
    {
        foreach ($this->backends as $backend) {
            if (!$backend->remove($key)) {
                return false;
            }
        }

        return true;
    }

    public function clear(): bool
    {
        foreach ($this->backends as $backend) {
            if (!$backend->clear()) {
                return false;
            }
        }

        return true;
    }

    public function setExpireTimeout(int $timeout): void
    {
        foreach ($this->backends as $backend) {
            $backend->setOption('expire', $timeout);
        }
    }

    public function toArray(): array
    {
        if (0 === count($this->backends)) {
            return [];
        }

        return $this->backends[array_key_first($this->backends)]->toArray();
    }
}
