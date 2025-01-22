<?php

namespace Hazaar\Model\Attribute;

use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Interface\AttributeRule;

/**
 * The Max rule is used to ensure that a value is less than a specified value.
 *
 * @param int $value the maximum value that the property can be
 *
 * @throws PropertyValidationException
 *
 * @example
 *
 * ```php
 * #[Max(10)]
 * public $my_property;
 * ```
 */
#[\Attribute]
class Max implements AttributeRule
{
    private int $value = 0;

    public function __construct(int $value)
    {
        $this->value = $value;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = min($propertyValue, $this->value);

        return true;
    }
}
