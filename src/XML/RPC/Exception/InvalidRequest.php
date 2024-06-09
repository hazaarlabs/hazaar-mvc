<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

class InvalidRequest extends \Exception
{
    public function __construct(string $remote)
    {
        parent::__construct("Invalid XMLRPC request received from {$remote}.");
    }
}
