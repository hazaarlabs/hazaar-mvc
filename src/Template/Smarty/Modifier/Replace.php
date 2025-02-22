<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Replace a string with another string.
 */
class Replace
{
    /**
     * @param string $string  The input string
     * @param string $search  The value to search for
     * @param string $replace The value to replace with
     */
    public function run(string $string, string $search, string $replace): string
    {
        return str_replace($search, $replace, $string);
    }
}
