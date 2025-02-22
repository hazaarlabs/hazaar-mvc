<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Print a variable using print_r.
 */
class PrintModifier
{
    /**
     * @param mixed $value The value to print
     */
    public function run(mixed $value): string
    {
        return print_r($value, true);
    }
}
