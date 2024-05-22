<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

use Hazaar\Exception;

class MethodNotFound extends Exception
{
    public function __construct(string $class, string $method_name)
    {
        parent::__construct("Method not found while trying to execute {$class}::{$method_name}()", 404);
    }
}
