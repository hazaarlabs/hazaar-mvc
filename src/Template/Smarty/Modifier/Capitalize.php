<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Capitalize a string.
 *
 * This modifier provides a method to capitalize a given string.
 */
class Capitalize
{
    /**
     * @param string $string the string to capitalize
     */
    public function run(string $string): string
    {
        return ucwords($string);
    }
}
