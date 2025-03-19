<?php

namespace Hazaar\Model\Attribute;

/**
 * The Format rule is used to format a property value according to a specified format.
 *
 * See: https://www.php.net/manual/en/function.sprintf.php
 *
 * @param string $format the format to apply to the property value
 *
 * @example
 *
 * ```php
 * #[Format('%s formatted')]
 * public $myProperty;
 * ```
 */
#[\Attribute]
class Format extends Base
{
    private string $format = '';

    public function __construct(string $format)
    {
        $this->format = $format;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        $propertyValue = sprintf($this->format, $propertyValue ?? '');

        return true;
    }
}
