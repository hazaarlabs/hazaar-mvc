<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Strip whitespace, newlines and tabs from a string.
 */
class Strip
{
    /**
     * @param string $string      The string to strip
     * @param string $replacement The replacement string
     */
    public function run(string $string, string $replacement = ''): string
    {
        return preg_replace('/[\s\n\t]+/', $replacement, $string);
    }
}
