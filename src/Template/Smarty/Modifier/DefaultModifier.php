<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Returns the default value if the provided value is null.
 */
class DefaultModifier
{
    /**
     * @param mixed $value   the value to check
     * @param mixed $default the default value to return if the provided value is null
     */
    public function run(mixed $value, mixed $default = null): mixed
    {
        if (null !== $value) {
            return $value;
        }

        return $default;
    }
}
