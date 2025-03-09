<?php

declare(strict_types=1);

namespace Hazaar\Util;

class Number
{
    public static function isEven(int $number): bool
    {
        return 0 === $number % 2;
    }
}
