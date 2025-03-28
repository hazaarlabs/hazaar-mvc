<?php

namespace Hazaar\Model\Attribute;

/**
 * The Range rule is used to ensure that a value is within a specified range.
 *
 * @param int $minValue the minimum value that the property can be
 * @param int $maxValue the maximum value that the property can be
 *
 * @example
 *
 * ```php
 * #[Range(0, 10)]
 * public $myProperty;
 * ```
 */
#[\Attribute]
class Range extends Base
{
    private int $minValue = 0;
    private int $maxValue = 0;

    public function __construct(int $minValue, int $maxValue)
    {
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = min(max($propertyValue, $this->minValue), $this->maxValue);

        return true;
    }
}
