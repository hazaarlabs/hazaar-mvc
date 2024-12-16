<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model;
use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Rule;

#[\Attribute]
class Max extends Rule
{
    private int $value = 0;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function evaluate(mixed $value, Model $model, \ReflectionProperty &$property): void
    {
        if ($value >= $this->value) {
            throw new PropertyValidationException($property->getName(), 'Max');
        }
    }
}
