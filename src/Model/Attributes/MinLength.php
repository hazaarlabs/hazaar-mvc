<?php

namespace Hazaar\Model\Attributes;

use Hazaar\Model\Interfaces\AttributeRule;

/**
 * The MinLength rule is used to ensure that a string is at least a certain length.
 *
 * @param int $length the minimum length of the string
 *
 * @example
 *
 * ```php
 * #[MinLength(10)]
 * public $my_property;
 * ```
 */
#[\Attribute]
class MinLength implements AttributeRule
{
    private int $length = 0;

    public function __construct(int $length)
    {
        $this->length = $length;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        if (empty($propertyValue)) {
            return true;
        }

        return strlen($propertyValue) >= $this->length;
    }
}
