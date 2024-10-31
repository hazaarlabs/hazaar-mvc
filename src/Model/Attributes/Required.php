<?php

namespace Hazaar\Model\Attributes;

use Hazaar\Model;
use Hazaar\Model\Exception\PropertyValidationException;

#[\Attribute]
class Required implements Model\Interfaces\Attribute
{
    public function check(Model $model, \ReflectionProperty &$property): void
    {
        if (false === $property->isInitialized($model)
            || empty($property->getValue($model))) {
            throw new PropertyValidationException($property->getName(), 'required');
        }
    }
}
