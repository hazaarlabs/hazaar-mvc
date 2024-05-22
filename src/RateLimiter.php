<?php

declare(strict_types=1);

namespace Hazaar;

use Hazaar\Application\Request\HTTP;

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

    /**
     * @var array<string,string>
     */
    private array $headers = [
        'X-RateLimit-Window' => '{{window}}',
        'X-RateLimit-Limit' => '{{limit}}',
        'X-RateLimit-Remaining' => '{{remaining}}',
    ];

    /**
     * RateLimiter constructor.
     *
     * @param array<string,mixed> $options the options for the rate limiter
     */
    public function __construct(array $options, ?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();
        $this->prefix = $options['prefix'] ?? $this->prefix;
        $this->windowLength = $options['window'] ?? 60;
        $this->requestLimit = $options['limit'] ?? 60;
    }

    /**
     * Retrieves information about a specific identifier.
     *
     * @param string $identifier the identifier to retrieve information for
     *
     * @return array<mixed> an array containing the information about the identifier
     */
    public function getInfo(string $identifier): array
    {
        $log = $this->get($identifier);

        return [
            'attempts' => count($log),
            'limit' => $this->requestLimit,
            'window' => $this->windowLength,
            'remaining' => $this->requestLimit - count($log),
        ];
    }

    /**
     * Retrieves the rate limit information for the specified identifier.
     *
     * @param string $identifier the identifier for which to retrieve the rate limit information
     *
     * @return array<mixed> the rate limit information for the specified identifier
     */
    public function get(string $identifier, ?int $time = null): array
    {
        $key = $this->getKey($identifier);
        $log = $this->cache->get($key);
        if (!$log) {
            $log = [];
        }
        if (!$time > 0) {
            $time = time();
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
     * @param string $identifier the identifier to check
     *
     * @return bool returns true if the identifier is allowed to proceed, false otherwise
     */
    public function check(string $identifier): bool
    {
        $now = time();
        $key = $this->getKey($identifier);
        $log = $this->get($identifier, $now);
        if (count($log) < $this->requestLimit) {
            // Log the current request timestamp
            $log[] = $now;
            $this->cache->set($key, $log, $this->windowLength * 2);

            return true;
        }

        return false; // Request limit exceeded
    }

    /**
     * Deletes a rate limiter entry identified by the given identifier.
     *
     * @param string $identifier the identifier of the rate limiter entry to delete
     */
    public function delete(string $identifier): void
    {
        $key = $this->getKey($identifier);
        $this->cache->remove($key);
    }

    /**
     * Retrieves the headers for the specified identifier.
     *
     * @param string $identifier the identifier for the headers
     *
     * @return array<string> the headers for the specified identifier
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
