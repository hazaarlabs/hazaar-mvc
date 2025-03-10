<?php

declare(strict_types=1);

namespace Hazaar\Util;

/**
 * Number utility class.
 *
 * This class provides a number of utility functions for working with numbers.
 */
class Number
{
    public static function isEven(int $number): bool
    {
        return 0 === $number % 2;
    }
}
