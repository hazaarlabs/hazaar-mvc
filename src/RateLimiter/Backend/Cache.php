<?php

namespace Hazaar\RateLimiter\Backend;

use Hazaar\Cache\Adapter;
use Hazaar\RateLimiter\Backend;

class Cache extends Backend
{
    private Adapter $cache;
    private string $prefix = 'rate_limiter_';

    /**
     * @var array<string,array<mixed>>
     */
    private array $index = [];

    /**
     * Cache rate limiter constructor.
     *
     * @param array<mixed> $options the options for the cache
     */
    public function __construct(array $options = [])
    {
        $this->cache = new Adapter(ake($options, 'type'), ake($options, 'cacheOptions', []));
        $this->cache->on(); // Force cache on even if no_pragma is set
        if (!$this->cache->can('lock')) {
            throw new \Exception('Cache backend does not support locking!');
        }
        $this->prefix = ake($options, 'prefix', $this->prefix);
    }

    public function shutdown(): void
    {
        foreach ($this->index as $identifier => $info) {
            $this->cache->set($this->getKey($identifier), $info);
        }
    }

    public function check(string $identifier): array
    {
        $info = $this->get($identifier);
        $info['log'][] = time();

        return $this->index[$identifier] = $info;
    }

    public function get(string $identifier): array
    {
        $key = $this->getKey($identifier);
        $info = $this->cache->get($key);
        if (!$info) {
            $info = [];
        }
        $time = time();
        if (isset($info['log'])) {
            foreach ($info['log'] as $index => $timestamp) {
                if ($timestamp < $time - $this->windowLength) {
                    unset($info['log'][$index]);
                }
            }
        } else {
            $info['log'] = [];
        }

        return $this->index[$identifier] = $info;
    }

    /**
     * Removes the rate limit information for the specified identifier.
     *
     * @param string $identifier the identifier for which to remove the rate limit information
     */
    public function remove(string $identifier): void
    {
        $key = $this->getKey($identifier);
        $this->cache->remove($key);
    }

    /**
     * Get the key for rate limiting based on the provided identifier.
     *
     * @param string $identifier the identifier used for rate limiting
     *
     * @return string the key for rate limiting
     */
    private function getKey(string $identifier): string
    {
        return $this->prefix.$identifier;
    }
}
