<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Interfaces\AttributeRule;

/**
 * The Min rule is used to ensure that a value is greater than a specified value.
 *
 * @param int $value the minimum value that the property can be
 *
 * @throws PropertyValidationException
 *
 * @example
 *
 * ```php
 * #[Min(10)]
 * public $my_property;
 * ```
 */
#[\Attribute]
class Min implements AttributeRule
{
    private int $value = 0;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = max($propertyValue, $this->value);

        return true;
    }
}
