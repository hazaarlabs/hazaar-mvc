<?php

namespace Hazaar\Model\Interface;

interface AttributeRule
{
    /**
     * Evaluate the sttrinbute rule against the value.
     *
     * @param mixed               $value    the value to evaluate
     * @param \ReflectionProperty $property the property being evaluated
     *
     * @return bool true if the value passes the rule, false otherwise
     */
    public function evaluate(mixed &$value, \ReflectionProperty &$property): bool;
}
