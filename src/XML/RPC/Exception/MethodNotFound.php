<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

class MethodNotFound extends \Exception
{
    public function __construct(string $method)
    {
        parent::__construct("Method '{$method}' is not a registered method.");
    }
}
