<?php

namespace Hazaar;

use Hazaar\Application\Request\HTTP;
use Hazaar\RateLimiter\Backend;
use Hazaar\RateLimiter\Backend\Cache;

/**
 * Class RateLimiter.
 *
 * This class is responsible for rate limiting HTTP requests.
 */
class RateLimiter
{
    private Backend $backend;
    private int $windowLength;
    private int $requestLimit;
    private int $requestMinimumPeriod = 0;
    private int $intervalThreshold = 0;
    private int $intervalMatch = 0;

    /**
     * @var array<string,string>
     */
    private array $headers = [
        'X-RateLimit-Window' => '{{window}}',
        'X-RateLimit-Limit' => '{{limit}}',
        'X-RateLimit-Attempts' => '{{attempts}}',
        'X-RateLimit-Remaining' => '{{remaining}}',
        'X-RateLimit-Identifier' => '{{identifier}}',
        'X-RateLimit-Cache' => '{{cache}}',
    ];

    /**
     * RateLimiter constructor.
     *
     * @param array<mixed> $options the options for the rate limiter
     * @param Backend|null $backend the backend to use for the rate limiter
     */
    public function __construct(array $options, ?Backend $backend = null)
    {
        $this->windowLength = $options['window'] ?? 60;
        $this->requestLimit = $options['limit'] ?? 60;
        $this->requestMinimumPeriod = $options['minimum'] ?? 0;
        $this->intervalThreshold = $options['intervalThreshold'] ?? 0;
        $this->intervalMatch = $options['intervalMatch'] ?? 0;
        $backendType = ake($options, 'backend', 'cache');
        if (null === $backend) {
            switch ($backendType) {
                case 'cache':
                    $this->backend = new Cache(['type' => 'shm'], $options);
                    break;
                case 'file':
                    $this->backend = new Backend\File($options);
                    break;
                default:
                    throw new \Exception('Invalid rate limiter backend type!');
            }
        } else {
            $this->backend = $backend;
        }
        $this->backend->setWindowLength($this->windowLength);
    }

    /**
     * Retrieves information about a specific identifier.
     *
     * @param string $identifier the identifier to retrieve information for
     *
     * @return array{attempts:int,limit:int,window:int,remaining:int,identifier:string} an array containing the information about the identifier
     */
    public function getInfo(string $identifier): array
    {
        $info = $this->backend->get($identifier);

        return [
            'attempts' => count($info['log']),
            'limit' => $this->requestLimit,
            'window' => $this->windowLength,
            'remaining' => max(0, $this->requestLimit - count($info['log'])),
            'identifier' => $identifier,
        ];
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
        $info = $this->backend->get($identifier);
        if (isset($info['result'])) {
            $info['last_result'] = $info['result'];
        }
        $info['result'] = $this->checkRateLimitRules($info);
        if (true === $info['result']) {
            $now = time();
            $info['last'] = $now;
            $info['log'][] = $now;
        }
        if (!$this->checkRequestIntervals($info)) {
            $info['result'] = false;
        }
        $this->backend->set($identifier, $info);

        return $info['result']; // Request limit exceeded
    }

    /**
     * Checks if the current request adheres to the rate limit rules.
     *
     * This method verifies two conditions:
     * 1. If the time since the last request is within the minimum period threshold.
     * 2. If the number of requests in the log exceeds the allowed request limit.
     *
     * @param array $info Reference to the array containing rate limit information.
     *                    - 'last': Timestamp of the last request.
     *                    - 'log': Array of timestamps of previous requests.
     *
     * @return bool returns true if the request is within the rate limit rules, false otherwise
     */
    private function checkRateLimitRules(array &$info): bool
    {
        $now = time();
        // Check if the last request is within the interval threshold
        if ($this->requestMinimumPeriod > 0
            && array_key_exists('last', $info)
            && $now < $info['last'] + $this->requestMinimumPeriod) {
            return false;
        }
        // Check if the last request exceeds the number of requests allowed in the interval window
        if (count($info['log']) >= $this->requestLimit) {
            return false;
        }

        return true;
    }

    /**
     * Checks if the last set of requests have been made within the specified interval threshold.
     *
     * This function evaluates the intervals between the last few requests to determine if they fall within
     * a defined threshold. If the requests are too frequent, it sets an interval lock to prevent further
     * processing until the interval condition is no longer met.
     *
     * @param array $info An associative array containing request log information and interval lock status.
     *                    - 'log' (array): A list of timestamps representing the times of the requests.
     *                    - 'intervalLock' (bool): A flag indicating if the interval lock is active.
     *
     * @return bool Returns true if the requests are within the allowed interval threshold or if there are
     *              not enough requests to check. Returns false if the interval lock is active or if the
     *              requests exceed the interval threshold.
     */
    private function checkRequestIntervals(array &$info): bool
    {
        // Check if the last x requests have been made within the interval threshold
        if (!($this->intervalThreshold > 0
            && $this->intervalMatch > 0
            && count($info['log']) >= ($this->intervalMatch + 2))) {
            // Unlock the interval lock if it is set when there are no longer enough requests to check
            $info['intervalLock'] = false;

            return true;
        }
        // If the interval lock is already set, return false
        if (isset($info['intervalLock'])
            && true === $info['intervalLock']) {
            return false;
        }
        $prevDiff = 0;
        $intervalMatch = 0;
        for ($i = count($info['log']) - 1; $i > 0; --$i) {
            $timeDiff = $info['log'][$i] - $info['log'][$i - 1];
            if ($prevDiff > 0) {
                $checkDiff = abs($timeDiff - $prevDiff);
                // If the difference between the last x requests is greater than the interval threshold, stop checking
                // This is because we want consecutive requests to be within the interval threshold
                if ($checkDiff > $this->intervalThreshold) {
                    break;
                }
                ++$intervalMatch;
                if ($intervalMatch >= $this->intervalMatch) {
                    $info['intervalLock'] = true;

                    return false;
                }
            }
            $prevDiff = $timeDiff;
        }

        return true;
    }

    /**
     * Retrieves the rate limiter entry identified by the given identifier.
     *
     * @return array<mixed> the rate limiter entry identified by the given identifier
     */
    public function get(string $identifier): array
    {
        return $this->backend->get($identifier);
    }

    /**
     * Deletes a rate limiter entry identified by the given identifier.
     *
     * @param string $identifier the identifier of the rate limiter entry to delete
     */
    public function delete(string $identifier): void
    {
        $this->backend->remove($identifier);
    }

    /**
     * Retrieves the headers for the specified identifier.
     *
     * @param string $identifier the identifier for the headers
     *
     * @return array<string,mixed> the headers for the specified identifier
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
        $info = $this->backend->get($identifier);

        return ake($info, 'last', 0);
    }
}
