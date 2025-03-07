<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Trims whitespace or other characters from the beginning and end of a string.
 */
class Trim
{
    /**
     * @param string $string The input string
     */
    public function run(string $string, string $characters = " \n\r\t\v\x00"): string
    {
        return trim($string, $characters);
    }
}
