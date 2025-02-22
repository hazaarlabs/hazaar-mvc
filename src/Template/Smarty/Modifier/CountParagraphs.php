<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Count the number of paragraphs in a string.
 */
class CountParagraphs
{
    /**
     * @param string $string the input string
     */
    public function run(string $string): int
    {
        return substr_count(preg_replace('/\n{2,}/', "\n", trim($string, "\n\n")), "\n") + 1;
    }
}
