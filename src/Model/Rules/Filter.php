<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model;
use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Rule;

#[\Attribute]
class Filter extends Rule
{
    private ?int $type = null;

    public function __construct(int $type)
    {
        $this->type = $type;
    }

    public function evaluate(mixed $value, Model $model, \ReflectionProperty &$property): void
    {
        if (null !== $this->type && !filter_var($value, $this->type)) {
            throw new PropertyValidationException($property->getName(), 'invalid filter');
        }
    }
}
