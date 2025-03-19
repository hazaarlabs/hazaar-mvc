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
     * @param string $decPoint     The character to use as the decimal point
     * @param string $thousandsSep The character to use as the thousands separator
     */
    public function run(mixed $value, int $decimals = 0, string $decPoint = '.', string $thousandsSep = ','): string
    {
        return number_format(floatval($value), $decimals, $decPoint, $thousandsSep);
    }
}
