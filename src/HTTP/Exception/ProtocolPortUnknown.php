<?php

declare(strict_types=1);

namespace Hazaar\HTTP\Exception;

use Hazaar\Exception;

class ProtocolPortUnknown extends Exception
{
    public function __construct(string $proto)
    {
        parent::__construct("Unable to find port for protocol '{$proto}'.  Please make sure your protocol is correct, and if so you may need to specify a port.");
    }
}
