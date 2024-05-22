<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

use Hazaar\Exception;

class HostNotFound extends Exception
{
    public function __construct(string $host)
    {
        parent::__construct("Host '{$host}' not found.  Please check the address and try again.");
    }
}
