<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class ExtenderMayNotInherit extends \Exception
{
    public function __construct(string $type, string $child, string $class)
    {
        parent::__construct("Class '{$child}' may not inherit from {$type} class '{$class}'.");
    }
}
