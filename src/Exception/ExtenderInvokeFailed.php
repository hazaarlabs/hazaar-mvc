<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class ExtenderInvokeFailed extends Exception
{
    public function __construct(string $access, string $class, string $method, string $parent)
    {
        parent::__construct("Trying to invoke {$access} method {$class}::{$method}() from scope {$parent}.");
    }
}
