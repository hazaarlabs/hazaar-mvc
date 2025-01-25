<?php

namespace Hazaar\Model\Attribute;

use Hazaar\Model\Exception\PropertyValidationException;

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
class Min extends Base
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
