<?php

namespace Hazaar\Model\Attribute;

/**
 * The Trim rule is used to trim a string of specified characters.
 *
 * @param string $char the character to trim from the property value
 *
 * @example
 *
 * ```php
 * #[Trim()]
 * public $myProperty;
 * ```
 */
#[\Attribute]
class Trim extends Base
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
