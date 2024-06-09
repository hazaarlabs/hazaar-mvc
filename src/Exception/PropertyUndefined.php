<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class PropertyUndefined extends \Exception
{
    public function __construct(string $class, string $property)
    {
        parent::__construct('Undefined property: '.$class.'::$'.$property);
    }
}
