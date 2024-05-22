<?php

declare(strict_types=1);

namespace Hazaar\Model\Exception;

use Hazaar\Exception;

class UnsetPropertyException extends Exception
{
    public function __construct(string $class, string $propertyName)
    {
        parent::__construct('Cannot unset property: '.$class.'::$'.$propertyName);
    }
}
