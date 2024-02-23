<?php

namespace Hazaar;

use Hazaar\Cache;
use Hazaar\Application\Request\Http;
use Hazaar\Controller\Response\HTTP\RateLimitExceeded;

/**
 * Class RateLimiter
 *
 * This class is responsible for rate limiting HTTP requests.
 */
class RateLimiter
{
    private Cache $cache;
    private string $prefix = 'rate_limiter_';
    private int $windowLength;
    private int $requestLimit;
    private array $headers = [
        'X-RateLimit-Window' => '{{windowLength}}',
        'X-RateLimit-Limit' => '{{requestLimit}}',
        'X-RateLimit-Remaining' => '{{currentAmmount}}'
    ];

    public function __construct(array $options, Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
        $this->prefix = $options['prefix'] ?? $this->prefix;
        $this->windowLength = $options['window'] ?? 60;
        $this->requestLimit = $options['limit'] ?? 60;
    }

    /**
     * Get the key for rate limiting based on the provided identifier.
     *
     * @param string $identifier The identifier used for rate limiting.
     * @return string The key for rate limiting.
     */
    private function getKey(string $identifier): string
    {
        return $this->prefix . $identifier;
    }

    /**
     * Retrieves the rate limit information for the specified identifier.
     *
     * @param string $identifier The identifier for which to retrieve the rate limit information.
     * @return array The rate limit information for the specified identifier.
     */
    public function get(string $identifier, int $time = null): array
    {
        $key = $this->getKey($identifier);
        $log = $this->cache->get($key);
        if(!$log) {
            $log =  [];
        }
        if(!$time > 0) {
            $time  = time();
        }
        foreach ($log as $index => $timestamp) {
            if ($timestamp < $time - $this->windowLength) {
                unset($log[$index]);
            }
        }
        return $log;
    }

    /**
     * Checks if the given identifier is allowed to proceed based on rate limiting rules.
     *
     * @param string $identifier The identifier to check.
     * @return bool Returns true if the identifier is allowed to proceed, false otherwise.
     */
    public function check(string $identifier): bool
    {
        $now = time();
        $key = $this->getKey($identifier);
        $log = $this->get($identifier, $now);
        if (count($log) < $this->requestLimit) {
            // Log the current request timestamp
            $log[] = $now;
            $this->cache->set($key, $log);
            return true;
        }
        return false; // Request limit exceeded
    }

    /**
     * Deletes a rate limiter entry identified by the given identifier.
     *
     * @param string $identifier The identifier of the rate limiter entry to delete.
     * @return void
     */
    public function delete(string $identifier): void
    {
        $key = $this->getKey($identifier);
        $this->cache->delete($key);
    }

    /**
     * Retrieves the headers for the specified identifier.
     *
     * @param string $identifier The identifier for the headers.
     * @return array The headers for the specified identifier.
     */
    public function getHeaders(string $identifier): array
    {
        $key = $this->getKey($identifier);
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[$key] = preg_replace_callback('/{{(.*?)}}/', function ($matches) use ($identifier) {
                switch ($matches[1]) {
                    case 'currentAmmount':
                        return $this->requestLimit - count($this->get($identifier));
                    case 'windowLength':
                        return $this->windowLength;
                    case 'requestLimit':
                        return $this->requestLimit;
                }
            }, $value);
        }

        return $headers;
    }

}
