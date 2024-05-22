<?php

declare(strict_types=1);

namespace Hazaar\Auth;

use Hazaar\Cache;
use Hazaar\Map;

class Helper extends Adapter\Session
{
    /**
     * Construct the helper.
     *
     * @param Map   $cacheConfig  The cache configuration
     * @param Cache $cacheBackend The cache backend to use
     */
    public function __construct(Map $cacheConfig = null, ?Cache $cacheBackend = null)
    {
        parent::__construct($cacheConfig, $cacheBackend);
        $this->identity = $this->session['hazaar_auth_identity'];
    }

    /**
     * Set the identity.
     *
     * @param string       $identity The identity
     * @param array<mixed> $extra    The extra data
     */
    public function queryAuth(string $identity, array $extra = []): array|bool
    {
        // Helper does not support queryAuth as it doesn't know how to look up credentials
        return false;
    }
}
