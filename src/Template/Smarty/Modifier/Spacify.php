<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Add spaces or other characters between each character in a string.
 */
class Spacify
{
    /**
     * @param string $string      The input string
     * @param string $replacement The replacement character
     */
    public function run(string $string, string $replacement = ' '): string
    {
        return implode($replacement, preg_split('//', $string, -1, PREG_SPLIT_NO_EMPTY));
    }
}
