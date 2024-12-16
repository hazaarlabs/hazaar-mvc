<?php

namespace Hazaar\Model\Rules;

use Hazaar\Model\Interfaces\AttributeRule;

#[\Attribute]
class Contains implements AttributeRule
{
    private string $value = '';

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function evaluate(mixed &$value, \ReflectionProperty &$property): bool
    {
        if (is_array($value)) {
            if (!in_array($this->value, $value)) {
                return false;
            }
        } elseif (!empty($value) && false === strpos($value, $this->value)) {
            return false;
        }

        return true;
    }
}
