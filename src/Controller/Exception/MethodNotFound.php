<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class MethodNotFound extends \Exception
{
    public function __construct(string $class, string $methodName)
    {
        parent::__construct("Method not found while trying to execute {$class}::{$methodName}()", 404);
    }
}
