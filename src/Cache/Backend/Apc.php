<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cache/Backend/Apc.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Cache\Backend;

use Hazaar\Cache\Backend;

/**
 * @brief The APC cache backend.
 *
 * @detail This is a high performance caching backend that uses user cache functions
 * that are part of the APC module.
 *
 * There are no special options required to use this backend.  It only requires that
 * the APC PHP module be installed and operational.
 */
class Apc extends Backend
{
    protected int $weight = 1;

    /**
     * @var array<string, mixed>
     */
    private array $refresh = [];

    public static function available(): bool
    {
        // Make sure APCU extension is loaded
        return in_array('apcu', get_loaded_extensions())
            // Check that we are either not on the CLI or that APCU CLI is enabled.
            && ('cli' !== \php_sapi_name() || '1' === ini_get('apc.enable_cli'));
    }

    public function init(string $namespace): void
    {
        $this->addCapabilities('store_objects', 'expire', 'array', 'expire_ns', 'expire_val', 'keepalive');
    }

    public function close(): bool
    {
        if (count($this->refresh) > 0) {
            foreach ($this->refresh as $key => &$value) {
                \apcu_store($this->key($key), $value, $this->options['lifetime']);
            }
        }

        return true;
    }

    public function has(string $key, bool $check_empty = false): bool
    {
        if (false === $check_empty) {
            return \apcu_exists($this->key($key));
        }
        $value = $this->get($key);

        return !empty($value);
    }

    public function get(string $key): mixed
    {
        $result = \apcu_fetch($this->key($key));
        if ($result
            && ($this->options['keepalive'] ?? false)
            && ($this->options['lifetime'] ?? 0) > 0) {
            $this->refresh[$key] = $result;
        }

        return $result;
    }

    public function set(string $key, mixed $value, int $timeout = 0): bool
    {
        if (!$timeout && ($this->options['lifetime'] ?? 0) > 0) {
            $timeout = $this->options['lifetime'];
        }
        if (array_key_exists($key, $this->refresh)) {
            unset($this->refresh[$key]);
        }

        return \apcu_store($this->key($key), $value, $timeout);
    }

    public function remove(string $key): bool
    {
        if (!\apcu_delete($this->key($key))) {
            return false;
        }
        if (isset($this->refresh[$key])) {
            unset($this->refresh[$key]);
        }

        return true;
    }

    public function clear(): bool
    {
        return apcu_clear_cache();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $iter = new \APCUIterator('/^'.$this->namespace.'::/');
        $array = [];
        $pos = strlen($this->namespace) + 2;
        foreach ($iter as $ns_key => $value) {
            $array[substr($ns_key, $pos)] = $value['value'];
        }

        return $array;
    }

    public function count(): int
    {
        $iter = new \APCUIterator('/^'.$this->namespace.'::/');

        return $iter->getTotalCount();
    }

    private function key(string $key): string
    {
        return $this->namespace.'::'.$key;
    }
}
