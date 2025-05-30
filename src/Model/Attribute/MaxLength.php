<?php

namespace Hazaar\Model\Attribute;

/**
 * The MaxLength rule is used to ensure that a string is at most a certain length.
 *
 * @param int $length the maximum length of the string
 *
 * @example
 *
 * ```php
 * #[MaxLength(10)]
 * public $myProperty;
 * ```
 */
#[\Attribute]
class MaxLength extends Base
{
    private int $length = 0;

    public function __construct(int $length)
    {
        $this->length = $length;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = substr($propertyValue ?? '', 0, $this->length);

        return true;
    }
}
