<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Replace part of a string with another string using a regular expression.
 */
class RegexReplace
{
    /**
     * @param string $string      The input string
     * @param string $pattern     The pattern to search for
     * @param string $replacement The replacement string
     */
    public function run(string $string, string $pattern = '//', string $replacement = ''): string
    {
        return preg_replace($pattern, $replacement, $string);
    }
}
