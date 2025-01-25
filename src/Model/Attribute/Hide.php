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
 * public $my_property;
 * ```
 */
#[\Attribute]
class Hide extends Base
{
    private ?string $context = null;

    /**
     * Create a new Required rule.
     *
     * @param string $context The context in which to hide the property
     */
    public function __construct(string $context)
    {
        $this->context = $context;
    }

    /**
     * Serializes the property value based on the given context.
     *
     * @param mixed               $propertyValue the value of the property to be serialized
     * @param \ReflectionProperty $property      the reflection property instance
     * @param null|string         $context       the context in which the serialization is taking place
     *
     * @return bool returns true if the context is not null and does not match the given context, otherwise false
     */
    public function serialize(mixed &$propertyValue, \ReflectionProperty &$property, ?string $context = null): bool
    {
        return null !== $this->context && $this->context !== $context;
    }
}
