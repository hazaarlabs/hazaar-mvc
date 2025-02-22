<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

/**
 * Format a number with grouped thousands.
 */
class NumberFormat
{
    /**
     * @param mixed  $value         The number to format
     * @param int    $decimals      The number of decimal points
     * @param string $dec_point     The character to use as the decimal point
     * @param string $thousands_sep The character to use as the thousands separator
     */
    public function run(mixed $value, int $decimals = 0, string $dec_point = '.', string $thousands_sep = ','): string
    {
        return number_format(floatval($value), $decimals, $dec_point, $thousands_sep);
    }
}
