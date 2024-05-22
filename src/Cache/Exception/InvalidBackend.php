<?php

declare(strict_types=1);

namespace Hazaar\Cache\Exception;

use Hazaar\Exception;

class InvalidBackend extends Exception
{
    public function __construct(string $class)
    {
        parent::__construct("Object of class '{$class}' is not a valid cache backend!");
    }
}
