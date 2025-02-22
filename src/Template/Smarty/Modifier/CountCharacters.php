<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Count the number of characters in a string.
 */
class CountCharacters
{
    /**
     * @param string $string            the input string
     * @param bool   $includeWhitespace whether to include whitespace in the count
     */
    public function run(string $string, bool $includeWhitespace = false): int
    {
        if (false === $includeWhitespace) {
            $string = str_replace(' ', '', $string);
        }

        return strlen($string);
    }
}
