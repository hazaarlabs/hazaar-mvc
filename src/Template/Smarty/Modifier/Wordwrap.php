<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Wraps a string to a given number of characters using a string break character.
 */
class Wordwrap
{
    /**
     * @param string $string The input string
     * @param int    $width  The number of characters at which the string will be wrapped
     * @param string $break  The line is broken using the optional break parameter
     * @param bool   $cut    If the cut is set to TRUE, the string is always wrapped at or before the specified width
     */
    public function run(string $string, int $width = 80, string $break = "\n", bool $cut = true): string
    {
        return wordwrap($string, $width, $break, $cut);
    }
}
