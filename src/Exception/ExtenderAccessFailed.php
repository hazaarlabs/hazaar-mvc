<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class ExtenderAccessFailed extends Exception
{
    public function __construct(string $access, string $class, string $property)
    {
        parent::__construct("Cannot access {$access} property {$class}::\${$property}");
    }
}
