<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Return the type of a variable.
 */
class Type
{
    /**
     * @param mixed $value The variable to get the type of
     */
    public function run(mixed $value): string
    {
        return gettype($value);
    }
}
