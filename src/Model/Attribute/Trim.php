<?php

namespace Hazaar\Model\Attribute;

use Hazaar\Model\Interface\AttributeRule;

/**
 * The Trim rule is used to trim a string of specified characters.
 *
 * @param string $char the character to trim from the property value
 *
 * @example
 *
 * ```php
 * #[Trim()]
 * public $my_property;
 * ```
 */
#[\Attribute]
class Trim implements AttributeRule
{
    private string $char;

    public function __construct(string $char = ' ')
    {
        $this->char = $char;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = trim($propertyValue ?? '', $this->char);

        return true;
    }
}
