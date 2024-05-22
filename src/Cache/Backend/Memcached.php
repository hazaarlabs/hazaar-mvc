<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Memcached.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Cache\Backend;
use Hazaar\Map;

/**
 * @brief The Memcached cache backend.
 *
 * @detail This backend uses the PHP memcached module to communicate with a memcached server.  It is pretty fast and allows for some
 * basic "clustering" (that isn't really clustering but thats what the memcached guys call it).  It's really just "not-so-fancy" partitioning.
 *
 * * server - The memcached server to connect to, or an array of servers.  Default: localhost.
 * * port - The port to connect to the server on.  Default: 11211
 * * use_compression - Enables compression on the communication link. Default: false
 */
class Memcached extends Backend
{
    protected int $weight = 2;
    private \Memcached $memcached;

    public static function available(): bool
    {
        $modules = get_loaded_extensions();

        return in_array('memcached', $modules);
    }

    public function init(string $namespace): void
    {
        $this->configure([
            'server' => 'localhost',
            'port' => 11211,
            'use_compression' => false,
        ]);
        $this->addCapabilities('store_objects');
        $this->memcached = new \Memcached($this->options->read('persistent_id', null));
        $servers = $this->options['server'];
        if (!$servers instanceof Map) {
            $servers = [$servers];
        }
        foreach ($servers as $server) {
            $this->memcached->addServer($server, $this->options['port']);
        }
        $this->memcached->setOption(\Memcached::OPT_COMPRESSION, $this->options['use_compression']);
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        $this->memcached->get($this->key($key));
        $result = $this->memcached->getResultCode();

        return !(\Memcached::RES_NOTFOUND == $result);
    }

    public function get(string $key): mixed
    {
        return $this->memcached->get($this->key($key));
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        return $this->memcached->set($this->key($key), $value, $timeout);
    }

    public function remove(string $key): bool
    {
        return $this->memcached->delete($this->key($key));
    }

    public function clear(): bool
    {
        return $this->memcached->flush();
    }

    public function toArray(): array
    {
        return [];
    }

    private function key(string $key): string
    {
        return $this->namespace.'::'.$key;
    }
}
