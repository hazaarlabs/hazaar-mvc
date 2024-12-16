<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model;
use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Rule;

#[\Attribute]
class Required extends Rule
{
    public function evaluate(mixed $value, Model $model, \ReflectionProperty &$property): void
    {
        if (empty($value)) {
            throw new PropertyValidationException($property->getName(), 'required');
        }
    }
}
