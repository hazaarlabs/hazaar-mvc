<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Implode values of an array using a glue string.
 */
class Implode
{
    /**
     * @param mixed  $array The array to implode
     * @param string $glue  The glue string to use
     */
    public function run(mixed $array, string $glue = ''): string
    {
        if (is_array($array)) {
            return implode($glue, $array);
        }

        return $array;
    }
}
