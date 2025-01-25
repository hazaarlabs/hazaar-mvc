<?php

namespace Hazaar\Model\Attribute;

#[\Attribute]
class Contains extends Base
{
    private string $value = '';

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        if (is_array($propertyValue)) {
            if (!in_array($this->value, $propertyValue)) {
                return false;
            }
        } elseif (!empty($propertyValue) && false === strpos($propertyValue, $this->value)) {
            return false;
        }

        return true;
    }
}
