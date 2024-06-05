<?php

declare(strict_types=1);

namespace Hazaar\Model\Exception;

use Hazaar\Exception\CallerException;

class PropertyException extends CallerException
{
    public function __construct(string $class, string $propertyName, string $reason)
    {
        parent::__construct("Typed property {$class}::\${$propertyName} {$reason}", 3);
    }
}
