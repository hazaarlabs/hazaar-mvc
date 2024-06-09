<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/File.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Application;
use Hazaar\BTree;
use Hazaar\Cache\Backend;

/**
 * @brief The file cache backend.
 *
 * @detail This backend will cache data to the filesystem.  It is basically a "works all the time" backend that is available
 * regardless of what modules are installed or server software is available.  It is very handing for using caching on
 * systems where no other cache backends are available.
 *
 * Available config options:
 *
 * * cache_dir - The directory to store cache files in.  Default is to use a 'cache' directory in the application .runtime directory.
 * * file_prefix - This is an optional prefix to apply to the cache files.  Useful if you want to separate your cache files.  Default: no prefix
 * * use_zlib - Enable or disable zlib compression on the cache files.  This can slow things down quite a bit more, but is useful when you
 * * encode_fs - Encodes the filesystem files using an md5 hash to obscure file namespaces on disk
 * are caching very large things.  I wouldn't bother using it under normal circumstances.  Default: false.
 */
class File extends Backend
{
    protected int $weight = 4;
    private bool $zlib = false;
    private string $cacheDir;
    private int $timeout = 0;

    /**
     * @var array<mixed>
     */
    private array $local = [];
    private BTree $store;

    /**
     * @var array<BTree>
     */
    private static array $__openStore = [];

    /**
     * Store the namespace timeout in the cache dir timeout file.
     *
     * This should only happen if a keepalive() has been called.
     */
    public function __destruct()
    {
        if ($this->timeout > 0) {
            $this->store->set('__namespace_timeout', $this->timeout);
        }
        unset($this->store);
    }

    public static function available(): bool
    {
        return true;
    }

    public function init(string $namespace): void
    {
        $app = Application::getInstance();
        $this->configure([
            'cache_dir' => $app instanceof Application ? $app->runtimePath('cache', true) : sys_get_temp_dir(),
            'file_prefix' => null,
            'use_zlib' => false,
            'encode_fs' => false,
            'keepalive' => false,
        ]);
        $this->cacheDir = $this->options['cache_dir']
            .(($this->options['file_prefix']) ? DIRECTORY_SEPARATOR.$this->options['file_prefix'] : null)
            .DIRECTORY_SEPARATOR;
        if (!file_exists($this->cacheDir)) {
            mkdir($this->cacheDir);
        }
        // Open the B-Tree database file
        $cacheFile = $this->cacheDir.($this->options['encode_fs'] ? md5($this->namespace) : $this->namespace).'.db';
        if (!array_key_exists($cacheFile, File::$__openStore)) {
            File::$__openStore[$cacheFile] = new BTree($cacheFile);
        }
        $this->store = File::$__openStore[$cacheFile];
        $this->addCapabilities('store_objects', 'expire_val', 'array');
        if (in_array('zlib', get_loaded_extensions())) {
            $this->zlib = true;
            $this->addCapabilities('compress');
        }
        if (!$this->options['encode_fs']) {
            $this->addCapabilities('array');
        }
        // If the lifetime value is greater than 0 then we support namespace timeouts.
        if (true === $this->options['keepalive'] && $this->options['lifetime'] > 0) {
            $this->addCapabilities('expire_ns', 'keepalive');
            // If a timeout exists, load it and check if we need to drop the namespace.
            if (!($timeout = $this->store->get('__namespace_timeout'))) {
                $timeout = 0;
            }
            // If the namespace has expired, drop it
            if (time() >= $timeout) {
                $this->clear();
            }
        }
    }

    /**
     * Check if a value exists.
     */
    public function has(string $key, bool $check_empty = false): bool
    {
        $value = $this->load($key);

        return "\0" !== $value && (true !== $check_empty || '' !== $value);
    }

    public function get(string $key): mixed
    {
        $value = $this->load($key);
        if ("\0" === $value) {
            return false;
        }

        return $value;
    }

    public function set(string $key, mixed $value, ?int $timeout = 0): bool
    {
        if ($this->zlib && $this->options['use_zlib']) {
            $value = gzcompress($value, 9);
        }
        $data = ['data' => $value];
        if ($timeout > 0) {
            $data['expire'] = time() + $timeout;
        }
        $this->keepalive();
        $this->local[$key] = $data;

        return $this->store->set($key, $data);
    }

    public function remove(string $key): bool
    {
        $this->keepalive();
        if (array_key_exists($key, $this->local)) {
            unset($this->local[$key]);
        }

        return $this->store->remove($key);
    }

    public function clear(): bool
    {
        $this->keepalive();
        $this->local = [];

        return $this->store->reset_btree_file();
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $array = [];
        $values = $this->store->range("\x00", "\xff");
        foreach ($values as $key => $cache) {
            if ('__' === substr($key, 0, 2)) {
                continue;
            }
            if (($value = $this->load($key, $cache)) !== "\0") {
                $array[$key] = $value;
            }
        }
        $this->keepalive();

        return $array;
    }

    public function count(): int
    {
        return count($this->toArray());
    }

    private function keepalive(): void
    {
        if (true === $this->options['keepalive'] && $this->options['lifetime'] > 0) {
            $this->timeout = time() + $this->options['lifetime'];
        }
    }

    /**
     * Load the key value from storage.
     *
     * This should only happen once and then it will be stored in memory and only written again when changed.
     *
     * @param ?array<mixed> $cache
     */
    private function load(string $key, ?array &$cache = null): mixed
    {
        if (!is_array($cache)) {
            if (!array_key_exists($key, $this->local)) {
                $this->local[$key] = $this->store->get($key);
            }
            $cache = &$this->local[$key];
        }
        $value = "\0";
        if (null === $cache) {
            return $value;
        }
        $expire = array_key_exists('expire', $cache) ? $cache['expire'] : null;
        if ($expire && $expire < time()) {
            $this->store->remove($key);
            $this->local[$key] = null;
        } else {
            if (array_key_exists('data', $cache)) {
                $value = $cache['data'];
            }
            if (is_string($value) && 120 === ord(substr($value, 0, 1))) {
                if (!$this->zlib) {
                    throw new Exception\NoZlib($key);
                }
                $value = gzuncompress($value);
            }
        }
        $this->keepalive();

        return $value;
    }
}
