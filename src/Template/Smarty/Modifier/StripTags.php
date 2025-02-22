<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Strip HTML tags from a string.
 */
class StripTags
{
    /**
     * @param string $string The string to strip HTML tags from
     */
    public function run(string $string): string
    {
        return preg_replace('/<[^>]+>/', '', $string);
    }
}
