<?php

declare(strict_types=1);

namespace Hazaar\Template\Smarty\Modifier;

use Hazaar\Util\DateTime;

/**
 * Formats a date using the specified format.
 */
class DateFormat
{
    /**
     * @param mixed       $item   The date to format. Can be a string, integer, or DateTime object.
     * @param null|string $format The format to use for the date. If not provided, the default format '%c' will be used.
     *
     * @return string the formatted date
     */
    public function run(mixed $item, ?string $format = null): string
    {
        if (!$item instanceof DateTime) {
            $item = new DateTime($item);
        }
        if (!$format) {
            $format = '%c';
        }

        return $item->format($format);
    }
}
