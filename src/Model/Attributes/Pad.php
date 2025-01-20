<?php

namespace Hazaar\Model\Attributes;

use Hazaar\Model\Interfaces\AttributeRule;

/**
 * The Pad rule is used to pad a string to a specified length.
 *
 * @param int $length the length to pad the string to
 *
 * @example
 *
 * ```php
 * #[Pad(10)]
 * public $my_property;
 * ```
 */
#[\Attribute]
class Pad implements AttributeRule
{
    private int $length = 0;

    public function __construct(int $length)
    {
        $this->length = $length;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = str_pad($propertyValue ?? '', $this->length);

        return true;
    }
}
