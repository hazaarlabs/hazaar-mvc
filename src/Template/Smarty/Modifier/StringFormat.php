<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Format a string using sprintf.
 */
class StringFormat
{
    /**
     * @param string $string The string to format
     * @param string $format The format string
     */
    public function run(string $string, string $format): string
    {
        return sprintf($format, $string);
    }
}
