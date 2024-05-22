<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

use Hazaar\Exception;

class NoConnection extends Exception
{
    public function __construct(string $host, int $errno, string $errstr)
    {
        parent::__construct("Unable to connect to {$host}.  ERR: {$errno}# - {$errstr}");
    }
}
