<?php

declare(strict_types=1);

namespace Hazaar\Model\Attribute;

use Hazaar\Model\Interface\AttributeRule;

abstract class Base implements AttributeRule
{
    public function evaluate(mixed &$propertyValue, \ReflectionProperty &$property): bool
    {
        return true;
    }

    public function serialize(mixed &$propertyValue, \ReflectionProperty &$property, ?string $context = null): bool
    {
        return true;
    }
}
