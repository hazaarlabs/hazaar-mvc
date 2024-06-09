<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class MethodUndefined extends \Exception
{
    public function __construct(string $class, string $method)
    {
        parent::__construct("Call to undefined method {$class}::{$method}()");
    }
}
