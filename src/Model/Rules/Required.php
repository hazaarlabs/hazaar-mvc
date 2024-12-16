<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model\Exception\PropertyValidationException;
use Hazaar\Model\Interfaces\AttributeRule;

/**
 * The Required rule is used to ensure that a value is not empty.
 *
 * @throws PropertyValidationException
 *
 * @example
 *
 * ```php
 * #[Required]
 * public $my_property;
 * ```
 */
#[\Attribute]
class Required implements AttributeRule
{
    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        return !empty($propertyValue);
    }
}
