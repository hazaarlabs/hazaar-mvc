<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Convert string to lowercase.
 */
class Lower
{
    /**
     * @param string $string The input string
     */
    public function run(string $string): string
    {
        return strtolower($string);
    }
}
