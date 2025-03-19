<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Indent a string with a specified number of spaces or a custom string.
 */
class Indent
{
    /**
     * @param string $string     The input string
     * @param int    $length     The number of spaces to indent the string
     * @param string $padString The string to use for indentation
     */
    public function run(string $string, int $length = 4, string $padString = ' '): string
    {
        return "\n".str_repeat($padString, $length).$string;
    }
}
