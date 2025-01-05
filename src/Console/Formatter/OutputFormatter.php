<?php

declare(strict_types=1);

namespace Hazaar\Console\Formatter;

class OutputFormatter
{
    public const COL_RED = "\033[31m";
    public const COL_GREEN = "\033[32m";
    public const COL_YELLOW = "\033[33m";
    public const COL_BLUE = "\033[34m";
    public const COL_MAGENTA = "\033[35m";
    public const COL_CYAN = "\033[36m";
    public const COL_WHITE = "\033[37m";
    public const COL_RESET = "\033[0m";

    public function format(string $string): string
    {
        return strip_tags($string);
    }
}
