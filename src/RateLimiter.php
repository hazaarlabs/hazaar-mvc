<?php

namespace Hazaar;

use Hazaar\Application\Request\Http;

/**
 * Class RateLimiter.
 *
 * This class is responsible for rate limiting HTTP requests.
 */
class RateLimiter
{
    private Cache $cache;
    private string $prefix = 'rate_limiter_';
    private int $windowLength;
    private int $requestLimit;
    private int $requestMinimumPeriod = 0;
    private array $headers = [
        'X-RateLimit-Window' => '{{window}}',
        'X-RateLimit-Limit' => '{{limit}}',
        'X-RateLimit-Attempts' => '{{attempts}}',
        'X-RateLimit-Remaining' => '{{remaining}}',
        'X-RateLimit-Identifier' => '{{identifier}}',
        'X-RateLimit-Cache' => '{{cache}}',
    ];

    public function __construct(array $options, ?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
        $this->cache->on(); // Force cache on even if no_pragma is set
        $this->prefix = $options['prefix'] ?? $this->prefix;
        $this->windowLength = $options['window'] ?? 60;
        $this->requestLimit = $options['limit'] ?? 60;
        $this->requestMinimumPeriod = $options['minimum'] ?? 0;
    }

    /**
     * Retrieves information about a specific identifier.
     *
     * @param string $identifier the identifier to retrieve information for
     *
     * @return array an array containing the information about the identifier
     */
    public function getInfo(string $identifier): array
    {
        $info = $this->get($identifier);

        return [
            'attempts' => count($info['log']),
            'limit' => $this->requestLimit,
            'window' => $this->windowLength,
            'remaining' => max(0, $this->requestLimit - count($info['log'])),
            'identifier' => $identifier,
            'cache' => $this->cache->getBackendName(),
        ];
    }

    /**
     * Retrieves the rate limit information for the specified identifier.
     *
     * @param string $identifier the identifier for which to retrieve the rate limit information
     *
     * @return array the rate limit information for the specified identifier
     */
    public function get(string $identifier, ?int $time = null, ?string &$key = null): array
    {
        $key = $this->getKey($identifier);
        $info = $this->cache->get($key);
        if (!$info) {
            $info = [];
        }
        if (!$time > 0) {
            $time = time();
        }
        if (isset($info['log'])) {
            foreach ($info['log'] as $index => $timestamp) {
                if ($timestamp < $time - $this->windowLength) {
                    unset($info['log'][$index]);
                }
            }
        } else {
            $info['log'] = [];
        }

        return $info;
    }

    /**
     * Checks if the given identifier is allowed to proceed based on rate limiting rules.
     *
     * @param string $identifier the identifier to check
     *
     * @return bool returns true if the identifier is allowed to proceed, false otherwise
     */
    public function check(string $identifier): bool
    {
        $now = time();
        $info = $this->get($identifier, $now, $key);
        if (isset($info['result'])) {
            $info['last_result'] = $info['result'];
        }
        if ($this->requestMinimumPeriod > 0
            && $now < $info['last'] + $this->requestMinimumPeriod) {
            $info['result'] = false;
        } else {
            $info['last'] = $now;
            if (count($info['log']) < $this->requestLimit) {
                // Log the current request timestamp
                $info['log'][] = $now;
                $info['result'] = true;
            } else {
                $info['result'] = false;
            }
        }
        $this->cache->set($key, $info, $this->windowLength * 2);

        return $info['result']; // Request limit exceeded
    }

    /**
     * Deletes a rate limiter entry identified by the given identifier.
     *
     * @param string $identifier the identifier of the rate limiter entry to delete
     */
    public function delete(string $identifier): bool
    {
        $key = $this->getKey($identifier);

        return $this->cache->remove($key);
    }

    /**
     * Retrieves the headers for the specified identifier.
     *
     * @param string $identifier the identifier for the headers
     *
     * @return array the headers for the specified identifier
     */
    public function getHeaders(string $identifier): array
    {
        $info = $this->getInfo($identifier);
        $headers = [];
        foreach ($this->headers as $key => $value) {
            $headers[$key] = preg_replace_callback('/{{(.*?)}}/', function ($matches) use ($info) {
                return ake($info, $matches[1], '');
            }, $value);
        }

        return $headers;
    }

    public function getLastRequestTime(string $identifier): int
    {
        $info = $this->get($identifier);

        return ake($info, 'last', 0);
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
