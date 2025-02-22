<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Truncate a string to a certain length if necessary, optionally splitting in the middle of a word.
 *
 * Truncated string will have a maximum length of $chars, but will not be cut in the middle of
 * a word unless $cut is set to true.  If $middle is set to true, the string will be truncated in
 * the middle and the text will be inserted in the middle of the string. If $middle is set to
 * true, $cut will be ignored. If the string is already shorter than $chars, it will be returned
 * as is. If the string is truncated, the $text string will be appended to the end of the string.
 */
class Truncate
{
    /**
     * @param string $string The string to truncate
     * @param int    $chars  The maximum length of the truncated string
     * @param string $text   The text to append to the truncated string
     * @param bool   $cut    If true, the string will be cut in the middle of a word
     * @param bool   $middle If true, the string will be truncated in the middle and the text will be inserted in the middle of the string
     */
    public function run(
        string $string,
        int $chars = 80,
        string $text = '...',
        bool $cut = false,
        bool $middle = false
    ): string {
        $chars -= strlen($text);
        if (strlen($string) <= $chars) {
            return $string;
        }
        if (true === $middle) {
            return substr($string, 0, $chars / 2).$text.substr($string, -($chars / 2));
        }
        $string = substr($string, 0, $chars);
        if (false === $cut && ($pos = strrpos($string, ' '))) {
            $string = substr($string, 0, $pos);
        }

        return $string.$text;
    }
}
