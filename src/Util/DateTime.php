<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Date.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Util;

if (!ini_get('date.timezone')) {
    ini_set('date.timezone', 'UTC');
}

/**
 * The date/time class.
 *
 * This class can be used to manage date and time values and perform date arithmetic.
 *
 * ### Example
 *
 * ```php
 * $date = new Hazaar\Util\DateTime('next tuesday');
 * echo $date; //Echo's a timestamp such as '2013-01-15 11:00:00.0'
 * ```
 *
 * ## Timezones
 *
 * It is possible to set the timezone globally for all date/time functions and classes.
 *
 * For example, to set your timezone to 'Australia/ACT', in your application.ini file add the line:
 *
 * pre. php.date.timezone = Australia/ACT
 *
 * See the PHP Manual for a [list of valid timezones](http://php.net/manual/en/timezones.php).
 *
 * If a timezone is not set in the application.ini file, nor is one set in the global PHP configuration then
 * as a last ditch effort the Date class will default to UTC. This is because not having an ini setting in
 * date.timzone will cause a PHP runtime error.
 */
class DateTime extends \DateTime implements \JsonSerializable, \DateTimeInterface
{
    public int $usec;

    /**
     * Global format for date part.
     */
    public static string $dateFormat = 'Y-m-d';

    /**
     * Global format for time part.
     */
    public static string $timeFormat = 'H:i:s';
    public static string $defaultFormat = 'l, F jS Y \a\t g:ia';
    private string $instanceFormat;

    /**
     * Date constructor.
     *
     * The Date class constructor takes two values. A datetime declaration and a timezone. Both are
     * optional. If now datetime is specified then the current datetime is used. If no timezone is
     * specified then the default timezone will be used.
     *
     * See the PHP documentation on [[http://au1.php.net/manual/en/function.strtotime.php|strtotime()]]
     * for more information on valid date time formats. If the datetime value is not set, the current
     * date and time will be used.  They can be either an integer representing the datetime Epoch
     * value (seconds since 1970-01-01) or a string datetime description supported by the PHP strtotime()
     * function. This means that textual datetime descriptions such as 'now' and 'next monday' will work.
     *
     * @param mixed                $datetime the datetime value you want to work with
     * @param \DateTimeZone|string $timezone The timezone for this datetime value. If no timezone
     *                                       is specified then the default timezone is used.
     */
    public function __construct(mixed $datetime = null, null|\DateTimeZone|string $timezone = null)
    {
        if (is_null($datetime)) {
            $datetime = '@'.microtime(true);
        } elseif (is_numeric($datetime)) {
            $datetime = '@'.$datetime.'.0';
        } elseif (is_array($datetime)) {
            if (array_key_exists('sec', $datetime)) { // Common array date object
                $ndatetime = '@'.$datetime['sec'];
                if (array_key_exists('usec', $datetime)) {
                    $ndatetime .= '.'.$datetime['usec'];
                }
                $datetime = $ndatetime;
            } elseif (array_key_exists('usec', $datetime) && array_key_exists('date', $datetime)) { // Array version of Hazaar\Util\DateTime
                if (!$timezone && array_key_exists('timezone', $datetime)) {
                    $timezone = $datetime['timezone'];
                }
                $datetime = '@'.strtotime($datetime['date']).'.'.$datetime['usec'];
            } else {
                $datetime = null;
            }
        } elseif ($datetime instanceof DateTime) {
            $datetime = '@'.$datetime->getTimestamp();
        } elseif (is_object($datetime)) {
            $datetime = (string) $datetime;
            if (is_numeric($datetime)) {
                $datetime = '@'.$datetime;
            }
        } elseif (is_string($datetime) && preg_match('/^(\w{2})([\d\.]+)$/', $datetime, $matches)) {
            // Convert MS "serial date" to epoch timestamp
            if ('SD' === $matches[1]) {
                $datetime = '@'.(int) ((floatval($matches[2]) - 25569) * 86400);
            }
        }
        if (preg_match('/@(\d+)\.(\d+)/', $datetime, $matches)) {
            $this->usec = (int) $matches[2];
            $datetime = '@'.$matches[1];
        }
        if ('@' == substr($datetime, 0, 1)) {
            if (!$timezone) {
                $timezone = new \DateTimeZone(date_default_timezone_get());
            }
            parent::__construct($datetime);
            $this->setTimezone($timezone);
        } else {
            if (preg_match('/\d{2}\:\d{2}\:\d{2}([\+\-][\d\:]+)$/', $datetime, $matches)) {
                $timezone = $matches[1];
            } elseif (!$timezone) {
                $timezone = date_default_timezone_get();
            } elseif (is_numeric($timezone)) {
                $timezone = timezone_identifiers_list()[(int) $timezone];
            }
            if (!$timezone instanceof \DateTimeZone) {
                if ('+00:00' == $timezone) {
                    $timezone = 'UTC';
                }
                $timezone = new \DateTimeZone($timezone);
            }
            parent::__construct($datetime, $timezone);
        }
    }

    /**
     * Magic method to output the datetime value as a string.
     * Uses the Date::timestamp() method.
     *
     * @return string string representation of the datetime value
     */
    public function __toString()
    {
        return $this->format(isset($this->instanceFormat) ? $this->instanceFormat : self::$defaultFormat);
    }

    public function __export(): int
    {
        return $this->sec();
    }

    public function setFormat(string $format): void
    {
        $this->instanceFormat = $format;
    }

    /**
     * Set the timezone for the date object.
     *
     * Setting this will cause the date and time to be output using the specified timezone. If a timezone is not given
     * then the default system timezone will be used. This is a good way to convert a date of another timezone into the
     * current default system timezone.
     *
     * @param \DateTimeZone|string $timezone The timezone to set. This can be either a [[DateTimeZone]] object, a string
     *                                       representation of a timezone, an offset in the format hh:mm, or a numeric value
     *                                       of the timezone returned by timezone_identifiers_list(). If it is left null, then
     *                                       the default system timezone is used.
     */
    public function setTimezone(null|\DateTimeZone|string $timezone = null): DateTime
    {
        if (null === $timezone) {
            $timezone = date_default_timezone_get();
        }
        if (is_numeric($timezone) && ($tz = (timezone_identifiers_list()[(int) $timezone] ?? 'UTC'))) {
            $timezone = $tz;
        }
        if (!$timezone instanceof \DateTimeZone) {
            if (is_numeric($timezone) && ($tz = timezone_name_from_abbr('', (int) $timezone, -1))) {
                $timezone = $tz;
            } elseif (preg_match('/([+-])?(\d+):(\d+)/', $timezone, $matches)) {
                if (!$matches[1]) {
                    $matches[1] = '+';
                }
                if ($timezoneName = timezone_name_from_abbr('', (int) ($matches[1].((((int) $matches[2]) * 3600) + (int) $matches[3])), -1)) {
                    $timezone = $timezoneName;
                }
            }
            $timezone = new \DateTimeZone($timezone);
        }
        parent::setTimezone($timezone);

        return $this;
    }

    /**
     * Return the date time value using a format string for a specified timezone.
     *
     * @param string $format
     *                         A standard date format string
     * @param string $timezone
     *                         The timezone to use to convert the date time value
     *
     * @return string the date time string in the target timezone and format
     */
    public function formatTZ($format, $timezone = 'UTC')
    {
        $timezoneBackup = date_default_timezone_get();
        date_default_timezone_set($timezone);
        $date = date($format, $this->getTimestamp());
        date_default_timezone_set($timezoneBackup);

        return $date;
    }

    /**
     * Get the current datetime value as an SQL compliant formatted string.
     *
     * @return string the SQL compliant datetime string
     */
    public function getSQLDate()
    {
        return $this->format('Y-m-d G:i:s').'.'.$this->usec;
    }

    /**
     * Returns the current datetime value as epoch (seconds passed since 1970-01-01).
     *
     * @return int epoch value of datetime
     */
    public function sec()
    {
        return (int) parent::getTimestamp();
    }

    /**
     * Return the microsecond part of the datetime value.
     *
     * This will more than likely always be 0 (zero) unless specified in the constructor.
     *
     * @return int microsecond part of datetime value
     */
    public function usec()
    {
        return $this->usec;
    }

    /**
     * Return the date part formatted by the global date format.
     *
     * @return string The date part of the datetime value
     */
    public function date()
    {
        $format = self::$dateFormat;

        return parent::format($format);
    }

    /**
     * Return the time part formatted by the global time format.
     *
     * @return string The time part of the datetime value
     */
    public function time()
    {
        $format = self::$timeFormat;

        return parent::format($format);
    }

    /**
     * Return the date and time.
     *
     * This will use the global date and time formats and concatenate them together, separated by a space.
     *
     * @return string The date and time of the datetime value
     */
    public function datetime()
    {
        $format = self::$dateFormat.' '.self::$timeFormat;

        return parent::format($format);
    }

    /**
     * Get the timestamp formatted as a string.
     *
     * This will use the global date and time formats and concatenate them together, separated by a space.
     *
     * @return string The timestamp as string
     */
    public function timestamp()
    {
        return parent::format('c');
    }

    /**
     * Returns the age in years of the date with optional precision.
     *
     * @param int $precision The number of digits to round an age to. Default: 0
     *
     * @return int the number of years passed since the date value
     */
    public function age(int $precision = 0): int
    {
        return (int) round(Interval::years(time() - $this->sec()), $precision);
    }

    /**
     * Returns the difference between the current date/time value and the value supplied as the first argument.
     *
     * Normally this method will return a \DateInterval object which is the default for the \DateTime class. However
     * this functionality has been extended with the $returnSeconds parameter which will instead return an integer
     * value indicating the difference in whole seconds.
     *
     * @param \DateTimeInterface $timestamp The timestamp to compare the current date/time to
     */
    public function diffSeconds(\DateTimeInterface $timestamp): int
    {
        $diff = parent::diff($timestamp);
        $seconds = 0;
        if ($diff->days > 0) {
            $seconds += $diff->days * 86400;
        }
        if ($diff->h > 0) {
            $seconds += $diff->h * 3600;
        }
        if ($diff->i > 0) {
            $seconds += $diff->i * 60;
        }
        if ($diff->s > 0) {
            $seconds += $diff->s;
        }
        if (0 == $diff->invert) {
            $seconds = -$seconds;
        }

        return $seconds;
    }

    /**
     * Returns the objects current year.
     */
    public function year(): int
    {
        return (int) $this->format('Y');
    }

    /**
     * Returns the objects current month.
     */
    public function month(): int
    {
        return (int) $this->format('m');
    }

    /**
     * Returns the objects current day.
     */
    public function day(): int
    {
        return (int) $this->format('d');
    }

    /**
     * Returns the objects current hour(.
     */
    public function hour(): int
    {
        return (int) $this->format('H');
    }

    /**
     * Returns the objects current minute.
     */
    public function minute(): int
    {
        return (int) $this->format('i');
    }

    /**
     * Returns the objects current second.
     */
    public function second(): int
    {
        return (int) $this->format('s');
    }

    /**
     * Add a date/time interval to the current date/time.
     *
     * See the PHP documentation on how to use the [DateInterval](http://au2.php.net/manual/en/class.dateinterval.php)
     * object.
     *
     * @param \DateInterval|string $interval  Can be either a \DateInterval object or a string representing an
     *                                        interval, such as P1H to specify 1 hour
     * @param bool                 $returnNew Doesn't update the current \Hazaar\Util\DateTime object and instead returns
     *                                        a new object with the interval applied
     */
    public function add(\DateInterval|string $interval, $returnNew = false): static
    {
        if (!$interval instanceof \DateInterval) {
            $interval = new \DateInterval($interval);
        }
        if ($returnNew) {
            $new = new DateTime($this, $this->getTimezone());

            // @phpstan-ignore-next-line
            return $new->add($interval);
        }
        parent::add($interval);

        return $this;
    }

    /**
     * Subtract a date/time interval from the current date/time.
     *
     * See the PHP documentation on how to use the [DateInterval](http://au2.php.net/manual/en/class.dateinterval.php) object.
     *
     * @param \DateInterval|string $interval  can be either a \DateInterval object or a string representing an interval, such as P1H to specify 1 hour
     * @param bool                 $returnNew doesn't update the current \Hazaar\Util\DateTime object and instead returns a new object with the interval applied
     */
    public function sub(\DateInterval|string $interval, bool $returnNew = false): static
    {
        if (!$interval instanceof \DateInterval) {
            $interval = new \DateInterval($interval);
        }
        if ($returnNew) {
            $new = new DateTime($this, $this->getTimezone());

            // @phpstan-ignore-next-line
            return $new->sub($interval);
        }
        parent::sub($interval);

        return $this;
    }

    /**
     * Compare the current date with the argument.
     */
    public function compare(mixed $date, bool $includeTime = false): bool
    {
        $format = 'Y-m-d'.($includeTime ? ' H:i:s' : null);
        if (!$date instanceof DateTime) {
            $date = new DateTime($date, $this->getTimezone());
        }

        return $this->format($format) == $date->format($format);
    }

    /**
     * Returns the start time of the current date as a Date object.
     */
    public function start(): DateTime
    {
        return new DateTime($this->format('Y-m-d 00:00:00'), $this->getTimezone());
    }

    /**
     * Returns the end time of the current date as a Date object.
     */
    public function end(): DateTime
    {
        return new DateTime($this->format('Y-m-d 23:59:59'), $this->getTimezone());
    }

    /**
     * Returns the first day of the current week as a Date object.
     */
    public function firstOfWeek(): DateTime
    {
        return $this->sub('P'.($this->format('N') - 1).'D', true)->setTime(0, 0, 0);
    }

    /**
     * Returns the last day of the current week as a Date object.
     */
    public function lastOfWeek(): DateTime
    {
        return $this->add('P'.(7 - $this->format('N')).'D', true)->setTime(23, 59, 59);
    }

    /**
     * Returns the first day of the current month as a Date object.
     */
    public function firstOfMonth(): DateTime
    {
        return new DateTime($this->format('Y-m-01 00:00:00'), $this->getTimezone());
    }

    /**
     * Returns the first day of the current year as a Date object.
     */
    public function firstOfYear(): DateTime
    {
        return new DateTime($this->format('Y-01-01 00:00:00'), $this->getTimezone());
    }

    /**
     * Returns the last day of the current year as a Date object.
     */
    public function lastOfYear(): DateTime
    {
        return new DateTime($this->format('Y-12-31 23:59:59'), $this->getTimezone());
    }

    /**
     * Return a fuzzy diff between the current time and the Date value.
     *
     * @param bool $precise           Boolean indicating if precise mode should be used.  This generally adds the time
     *                                to day-based results.
     * @param int  $dateThresholdDays A threshold in days after which the full date will be returned.  Avoids
     *                                situations like "3213 days ago" which is silly.
     *
     * @return string returns a nice fuzzy interval like "yesterday at xx:xx" or "4 days ago"
     */
    public function getFuzzyDiff(bool $precise = false, int $dateThresholdDays = 30): string
    {
        $diff = $this->diff(new DateTime(null, $this->getTimezone()));
        if ($diff->days > $dateThresholdDays) {
            return $this->format('F jS'.($precise ? ' \a\t g:ia' : ''));
        }
        if (0 === $diff->days) {
            if ($diff->h > 0) {
                $msg = $diff->h.' hour'.(($diff->h > 1) ? 's' : null);
            } elseif ($diff->i > 0) {
                $msg = $diff->i.' minute'.(($diff->i > 1) ? 's' : null);
            } elseif (false == $precise && $diff->s < 30) {
                $msg = 'A few seconds';
            } else {
                $msg = $diff->s.' seconds';
            }
            $msg .= ' ago';
        } elseif (1 === $diff->days) {
            $msg = 'Yesterday'.($precise ? ' at '.$this->format('g:ia') : '');
        } elseif ($diff->days > 1 && $diff->days < 7) {
            $msg = 'Last '.$this->format('l'.($precise ? ' \a\t g:ia' : ''));
        } else {
            $msg = $diff->days.' days ago';
        }

        return $msg;
    }

    /**
     * Get locale data for a specified locale.
     *
     * Data included is what is returned by PHP's localeconv() function.
     *
     * @param null|array<string>|string $locale the locale to get data for
     *
     * @return array<string>|bool If the locale is invalid then FALSE is returned.  Otherwise the result of
     *                            localeconv() for the specified locale.
     */
    public static function getLocaleData(null|array|string $locale): array|bool
    {
        $localLocale = setlocale(LC_ALL, null);
        if (!setlocale(LC_ALL, $locale)) {
            return false;
        }
        $data = localeconv();
        setlocale(LC_ALL, $localLocale);

        return $data;
    }

    /**
     * Retrieve a date format for a specific locale.
     *
     * This will return something like DMY to indicate that the locale format is date, month followed by year.
     *
     * These formats are not meant to be used directly in date functions as different date functions use different
     * format specifiers and we won't even attempt to support all of them here.
     *
     * @param null|array<string>|string $locale
     */
    public static function getLocaleDateFormat(null|array|string $locale): bool|string
    {
        $localLocale = setlocale(LC_ALL, null);
        if (!setlocale(LC_ALL, $locale)) {
            return false;
        }
        $format = null;
        if (preg_match('/(\d+)(\W)(\d+)(\W)(\d+)/', self::ftime('%c', mktime(0, 0, 0, 12, 1, 2000)), $matches)) {
            $matrix = [1 => 'D', 12 => 'M', 2000 => 'Y'];
            $format = $matrix[(int) $matches[1]].$matrix[(int) $matches[3]].$matrix[(int) $matches[5]];
        }
        setlocale(LC_ALL, $localLocale);

        return $format;
    }

    /**
     * Outputs the UTC timestamp (EPOCH) When an object is included in a json_encode call.
     */
    public function jsonSerialize(): mixed
    {
        return isset($this->instanceFormat) ? parent::format($this->instanceFormat) : $this->timestamp();
    }

    /**
     * Hazaar implementation of the str_ftime function.
     *
     * This function is a replacement for the deprecated strftime function.  It is not a complete replacement
     * but it does provide a lot of the functionality that strftime does.  It is not a drop-in replacement
     * for strftime as it does not support all the same format specifiers.
     *
     * The following format specifiers are supported:
     * - %a An abbreviated textual representation of the day	Sun through Sat
     * - %A A full textual representation of the day	Sunday through Saturday
     * - %d Two-digit day of the month (with leading zeros)	01 to 31
     * - %e Day of the month, with a space preceding single digits. Not implemented as described on Windows. See below for more information.	1 to 31
     * - %j Day of the year, 3 digits with leading zeros	001 to 366
     * - %u ISO-8601 numeric representation of the day of the week	1 (for Monday) through 7 (for Sunday)
     * - %w Numeric representation of the day of the week	0 (for Sunday) through 6 (for Saturday)
     * - %U Week number of the given year, starting with the first Sunday as the first week	13 (for the 13th full week of the year)
     * - %V ISO-8601:1988 week number of the given year, starting with the first week of the year with at least 4 weekdays, with Monday being the start of the week	01 through 53 (where 53 accounts for an overlapping week)
     * - %W A numeric representation of the week of the year, starting with the first Monday as the first week	46 (for the 46th week of the year beginning with a Monday)
     * - %b Abbreviated month name, based on the locale	Jan through Dec
     * - %B Full month name, based on the locale	January through December
     * - %h Abbreviated month name, based on the locale (an alias of %b)	Jan through Dec
     * - %m Two digit representation of the month	01 (for January) through 12 (for December)
     * - %C Two digit representation of the century (year divided by 100, truncated to an integer)	19 for the 20th Century
     * - %g Two digit representation of the year going by ISO-8601:1988 standards (see %V)	Example: 09 for the week of January 6, 2009
     * - %G The full four-digit version of %g	Example: 2008 for the week of January 3, 2009
     * - %y Two digit representation of the year	Example: 09 for 2009, 79 for 1979
     * - %Y Four digit representation for the year	Example: 2038
     * - %H Two digit representation of the hour in 24-hour format	00 through 23
     * - %k Hour in 24-hour format, with a space preceding single digits	0 through 23
     * - %I Two digit representation of the hour in 12-hour format	01 through 12
     * - %l (lower-case 'L')	Hour in 12-hour format, with a space preceding single digits	1 through 12
     * - %M Two digit representation of the minute	00 through 59
     * - %p UPPER-CASE 'AM' or 'PM' based on the given time	Example: AM for 00:31, PM for 22:23
     * - %P lower-case 'am' or 'pm' based on the given time	Example: am for 00:31, pm for 22:23
     * - %r Same as "%I:%M:%S %p"	Example: 09:34:17 PM for 21:34:17
     * - %R Same as "%H:%M"	Example: 00:35 for 12:35 AM, 16:44 for 4:44 PM
     * - %S Two digit representation of the second	00 through 59
     * - %T Same as "%H:%M:%S"	Example: 21:34:17 for 09:34:17 PM
     * - %X Preferred time representation based on locale, without the date	Example: 03:59:16 or 15:59:16
     * - %z The time zone offset. Not implemented as described on Windows. See below for more information.	Example: -0500 for U.S. Eastern Time
     * - %Z Time zone name. Not implemented as described on Windows. See below for more information.	Example: EST for Eastern Time
     * - %s The number of seconds since the Unix Epoch	Example: 1390948122
     * - %n A newline character ("\n")
     * - %t A Tab character ("\t")
     * - %% A literal percentage character ("%")
     *
     * @param string $format The format string to use defined by the original `strftime()` function
     */
    public static function ftime(string $format, ?int $timestamp = null): string
    {
        if (!$timestamp) {
            $timestamp = time();
        }
        $map = [
            'a' => 'D',	    // An abbreviated textual representation of the day	Sun through Sat
            'A' => 'l',	    // A full textual representation of the day	Sunday through Saturday
            'd' => 'd',	    // Two-digit day of the month (with leading zeros)	01 to 31
            'e' => 'j',	    // Day of the month, with a space preceding single digits. Not implemented as described on Windows. See below for more information.	1 to 31
            'j' => 'z',	    // Day of the year, 3 digits with leading zeros	001 to 366
            'u' => 'N',	    // ISO-8601 numeric representation of the day of the week	1 (for Monday) through 7 (for Sunday)
            'w' => 'w',	    // Numeric representation of the day of the week	0 (for Sunday) through 6 (for Saturday)
            // Week	---	---
            'U' => 'W',	    // Week number of the given year, starting with the first Sunday as the first week	13 (for the 13th full week of the year)
            'V' => 'W',	    // ISO-8601:1988 week number of the given year, starting with the first week of the year with at least 4 weekdays, with Monday being the start of the week	01 through 53 (where 53 accounts for an overlapping week)
            'W' => 'W',	    // A numeric representation of the week of the year, starting with the first Monday as the first week	46 (for the 46th week of the year beginning with a Monday)
            // Month	---	---
            'b' => 'M',	    // Abbreviated month name, based on the locale	Jan through Dec
            'B' => 'F',	    // Full month name, based on the locale	January through December
            'h' => 'M',	    // Abbreviated month name, based on the locale (an alias of %b)	Jan through Dec
            'm' => 'm',	    // Two digit representation of the month	01 (for January) through 12 (for December)
            // Year	---	---
            'C' => '',	    // Two digit representation of the century (year divided by 100, truncated to an integer)	19 for the 20th Century
            'g' => 'y',	    // Two digit representation of the year going by ISO-8601:1988 standards (see %V)	Example: 09 for the week of January 6, 2009
            'G' => 'Y',	    // The full four-digit version of %g	Example: 2008 for the week of January 3, 2009
            'y' => 'y',	    // Two digit representation of the year	Example: 09 for 2009, 79 for 1979
            'Y' => 'Y',	    // Four digit representation for the year	Example: 2038
            // Time	---	---
            'H' => 'H',	    // Two digit representation of the hour in 24-hour format	00 through 23
            'k' => 'G',	    // Hour in 24-hour format, with a space preceding single digits	0 through 23
            'I' => 'h',	    // Two digit representation of the hour in 12-hour format	01 through 12
            'l' => 'g',     // (lower-case 'L')	Hour in 12-hour format, with a space preceding single digits	1 through 12
            'M' => 'i', 	// Two digit representation of the minute	00 through 59
            'p' => 'A', 	// UPPER-CASE 'AM' or 'PM' based on the given time	Example: AM for 00:31, PM for 22:23
            'P' => 'a',	    // lower-case 'am' or 'pm' based on the given time	Example: am for 00:31, pm for 22:23
            'r' => 'h:i:s a', // Same as "%I:%M:%S %p"	Example: 09:34:17 PM for 21:34:17
            'R' => 'H:i', 	// Same as "%H:%M"	Example: 00:35 for 12:35 AM, 16:44 for 4:44 PM
            'S' => 's', 	// Two digit representation of the second	00 through 59
            'T' => 'H:i:s',	// Same as "%H:%M:%S"	Example: 21:34:17 for 09:34:17 PM
            'X' => 'H:i:s',	// Preferred time representation based on locale, without the date	Example: 03:59:16 or 15:59:16
            'z' => 'O',	    // The time zone offset. Not implemented as described on Windows. See below for more information.	Example: -0500 for US Eastern Time
            'Z' => 'T',	    // The time zone abbreviation. Not implemented as described on Windows. See below for more information.	Example: EST for Eastern Time
            // Time and Date Stamps	---	---
            'c' => 'r',	    // Preferred date and time stamp based on locale	Example: Tue Feb 5 00:45:10 2009 for February 5, 2009 at 12:45:10 AM
            'D' => 'm/d/y',	// Same as "%m/%d/%y"	Example: 02/05/09 for February 5, 2009
            'F' => 'Y-m-d',	// Same as "%Y-%m-%d" (commonly used in database datestamps)	Example: 2009-02-05 for February 5, 2009
            's' => 'U',	    // Unix Epoch Time timestamp (same as the time() function)	Example: 305815200 for September 10, 1979 08:40:00 AM
            // 'x' => 'r',	    //Preferred date representation based on locale, without the time	Example: 02/05/09 for February 5, 2009
            // 'x' is removed because there is no way to equivalent in the date() function
            // Miscellaneous	---	---
            'n' => "\n",	// A newline character ("\n")	---
            't' => "\t",	// A Tab character ("\t")	---
            '%' => '%',      // A literal percentage character ("%")
        ];
        $mapped_format = preg_replace_callback('/\%(\w)/', function ($match) use ($map) {
            return isset($map[$match[1]]) ? $map[$match[1]] : '';
        }, $format);

        return date($mapped_format, $timestamp);
    }
}
