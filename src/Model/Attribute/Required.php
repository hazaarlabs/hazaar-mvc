<?php

namespace Hazaar\Model\Attribute;

use Hazaar\Model\Exception\PropertyValidationException;

/**
 * The Required rule is used to ensure that a value is not empty.
 *
 * @throws PropertyValidationException
 *
 * @example
 *
 * ```php
 * #[Required]
 * public $myProperty;
 * ```
 */
#[\Attribute]
class Required extends Base
{
    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        return !empty($propertyValue);
    }
}
