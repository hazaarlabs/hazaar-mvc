<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter;

use Hazaar\Auth\Adapter;
use Hazaar\Map;

class Basic extends Adapter
{
    /**
     * Construct the helper.
     */
    public function __construct(?Map $config = null)
    {
        parent::__construct($config);
    }

    /**
     * Set the identity.
     *
     * @param string        $identity The identity
     * @param array<string> $extra    Extra data to return with the authentication
     */
    public function queryAuth(string $identity, array $extra = null)
    {
        // Helper does not support queryAuth as it doesn't know how to look up credentials
        return false;
    }
}
