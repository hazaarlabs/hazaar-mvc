<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

use Hazaar\Util\Boolean;

/**
 * Count the number of words in a string.
 */
class Yn
{
    /**
     * @param string $string the input string
     *
     * @return string the string 'Yes' if the input string is 'true', 'false' or '1', 'No' otherwise
     */
    public function run(mixed $string, string $trueValue = 'Yes', string $faleValue = 'No'): string
    {
        return Boolean::yn($string, $trueValue, $faleValue);
    }
}
