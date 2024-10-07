<?php

namespace Hazaar\RateLimiter\Interfaces;

interface Backend
{
    /**
     * Retrieves the rate limit information for the specified identifier and adds the current timestamp to the log.
     *
     * @param string $identifier the identifier for which to retrieve the rate limit information
     *
     * @return array{log:array<int>,last:int,result?:bool,last_result?:bool} the rate limit information for the specified identifier
     */
    public function check(string $identifier): array;

    /**
     * Retrieves the rate limit information for the specified identifier without adding the current timestamp to the log.
     *
     * @param string $identifier the identifier for which to retrieve the rate limit information
     *
     * @return array{log:array<int>,last:int,result?:bool,last_result?:bool} the rate limit information for the specified identifier
     */
    public function get(string $identifier): array;

    /**
     * Removes the rate limit information for the specified identifier.
     *
     * @param string $identifier the identifier for which to remove the rate limit information
     */
    public function remove(string $identifier): void;

    /**
     * Shutdown the rate limiter backend a commit any changes.
     */
    public function shutdown(): void;
}
