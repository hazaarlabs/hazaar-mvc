<?php

declare(strict_types=1);

namespace Hazaar\Util;

/**
 * Interval utility class.
 *
 * This class provides a number of helper functions for working with time intervals.
 */
class Interval
{
    /**
     * Convert a string interval to seconds.
     *
     * This function can be used to convert a string interval such as '1 week' into seconds. Currently
     * supported intervals are seconds, minutes, hours, days and weeks. Months are not supported because
     * some crazy crackpot decided to make them all different lengths, so without knowing which month we're
     * talking about, converting them to seconds is impossible.
     *
     * Multiple interval support is also possible. Intervals can be separated with a comma (,) or the word
     * 'and', for example:
     *
     * ```php
     * $foo = seconds('1 week, 2 days');
     * $bar = seconds('1 week and 2 days');
     * ```
     *
     * Both of these function calls will yeild the same result.
     *
     * @param string $interval The string interval to convert to seconds
     *
     * @return int Number of seconds in the interval
     */
    public static function seconds(string $interval): int
    {
        $intervals = preg_split('/(\s+and|\s*,)\s+/', $interval);
        $value = 0;
        foreach ($intervals as $interval) {
            if (!preg_match('/(\d+)\s*(\w+)/', $interval, $matches)) {
                return 0;
            }
            $val = (int) $matches[1];

            switch (strtolower($matches[2])) {
                case 's':
                case 'second':
                case 'seconds':
                    $value += $val;

                    break;

                case 'm':
                case 'minute':
                case 'minutes':
                    $value += ($val * 60);

                    break;

                case 'h':
                case 'hour':
                case 'hours':
                    $value += ($val * 60 * 60);

                    break;

                case 'd':
                case 'day':
                case 'days':
                    $value += ($val * 60 * 60 * 24);

                    break;

                case 'w':
                case 'week':
                case 'weeks':
                    $value += ($val * 60 * 60 * 24 * 7);

                    break;

                case 'y':
                case 'year':
                case 'years':
                    $value = ($val * 60 * 60 * 24 * 365.25);

                    break;
            }
        }

        return $value;
    }

    /**
     * Convert interval to minutes.
     *
     * Same as the seconds function except returns the number of minutes.
     *
     * @return float Minutes in interval
     */
    public static function minutes(int $interval): float
    {
        return $interval / 60;
    }

    /**
     * Convert interval to hours.
     *
     * Same as the seconds function except returns the number of hours.
     *
     * @return float Hours in interval
     */
    public static function hours(int $interval): float
    {
        return self::minutes($interval) / 60;
    }

    /**
     * Convert interval to days.
     *
     * Same as the seconds function except returns the number of days.
     *
     * @return float Days in interval
     */
    public static function days(int $interval): float
    {
        return self::hours($interval) / 24;
    }

    /**
     * Convert interval to weeks.
     *
     * Same as the seconds function except returns the number of weeks.
     *
     * @return float Weeks in interval
     */
    public static function weeks(int $interval): float
    {
        return self::days($interval) / 7;
    }

    /**
     * Convert interval to years.
     *
     * Same as the seconds function except returns the number of years.
     *
     * @return float Years in interval
     */
    public static function years(int $interval): float
    {
        return self::days($interval) / 365.25;
    }

    /**
     * Get the age of a date.
     *
     * This helper function will return the number of years between a specified date and now. Useful for
     * getting an age.
     *
     * @return int number of years from the specified date to now
     */
    public static function age(DateTime|int|string $date): int
    {
        if ($date instanceof DateTime) {
            $time = $date->getTimestamp();
        } elseif (is_string($date)) {
            $time = strtotime($date);
        } else {
            $time = $date;
        }

        return (int) floor(self::years(time() - $time));
    }

    /**
     * Return a string interval in a nice readable format.
     *
     * Similar to uptime() this extends the format into a complete string in a nice, friendly readable format.
     *
     * @param mixed $seconds the interval to convert in seconds
     *
     * @return string a friendly string
     */
    public static function toString(mixed $seconds): string
    {
        if ($seconds < 1) {
            return abs($seconds - floor($seconds)) * 1000 .'ms';
        }
        $seconds = (int) $seconds;
        $units = [
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];
        $output = [];
        foreach ($units as $name => $value) {
            if ($seconds >= $value) {
                $count = floor($seconds / $value);
                $output[] = $count.' '.$name.($count > 1 ? 's' : '');
                $seconds %= $value;
            }
        }

        return implode(', ', $output);
    }

    /**
     * Convert interval to uptime string.
     *
     * This function will convert an integer of seconds into an uptime string similar to what is returned by
     * the unix uptime command. ie: '9 days 3:32:02'
     */
    public static function uptime(int $interval): string
    {
        $d = floor(self::days((int) $interval));
        $h = (string) (floor(self::hours((int) $interval)) - ($d * 24));
        $m = (string) (floor(self::minutes((int) $interval)) - (($h + ($d * 24)) * 60));
        $s = (string) (floor($interval) - (($m + ($h + ($d * 24)) * 60) * 60));
        $o = '';
        if (1 == $d) {
            $o .= "{$d} day ";
        } elseif ($d > 1) {
            $o .= "{$d} days ";
        }
        $o .= $h.':'.str_pad($m, 2, '0', STR_PAD_LEFT).':'.str_pad($s, 2, '0', STR_PAD_LEFT);

        return $o;
    }
}
