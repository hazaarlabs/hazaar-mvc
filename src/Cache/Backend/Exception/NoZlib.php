<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

use Hazaar\Exception;

class NoZlib extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct("ZLib compression is not available while readying cache key '{$key}'");
    }
}
