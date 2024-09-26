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
        $now = time();
        $info = $this->backend->check($identifier);
        if (isset($info['result'])) {
            $info['last_result'] = $info['result'];
        }
        if ($this->requestMinimumPeriod > 0
            && $now < $info['last'] + $this->requestMinimumPeriod) {
            $info['result'] = false;
        } else {
            $info['last'] = $now;
            if (count($info['log']) <= $this->requestLimit) {
                // Log the current request timestamp
                $info['result'] = true;
            } else {
                $info['result'] = false;
            }
        }
        if (true === $info['result']) {
            $this->backend->set($identifier, $info);
            $this->backend->commit();
        }

        return $info['result']; // Request limit exceeded
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
