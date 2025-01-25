<?php

namespace Hazaar\Model\Interface;

interface AttributeRule
{
    /**
     * Evaluate the attribute rule against the value.
     *
     * @param mixed               $value    the value to evaluate
     * @param \ReflectionProperty $property the property being evaluated
     *
     * @return bool true if the value passes the rule, false otherwise
     */
    public function evaluate(mixed &$value, \ReflectionProperty &$property): bool;

    /**
     * Evaluate the attribute rule against the value when serializing.
     *
     * @param mixed               $value    the value to serialize
     * @param \ReflectionProperty $property the property being serialized
     *
     * @return bool true if the value can be serialized, false otherwise
     */
    public function serialize(mixed &$value, \ReflectionProperty &$property, ?string $context = null): bool;
}
