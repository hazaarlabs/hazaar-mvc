<?php

declare(strict_types=1);

namespace Hazaar\Model\Exception;

use Hazaar\Exception;

class PropertyValidationException extends Exception
{
    public function __construct(string $propertyName, string $ruleName)
    {
        parent::__construct("Property '{$propertyName}' failed to validate with rule '{$ruleName}'.");
    }
}
