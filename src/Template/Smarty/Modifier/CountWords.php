<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Count the number of words in a string.
 */
class CountWords
{
    /**
     * @param string $string the input string
     *
     * @return int the number of words in the string
     */
    public function run(string $string): int
    {
        return str_word_count($string);
    }
}
