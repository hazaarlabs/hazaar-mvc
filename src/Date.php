<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Date.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

if (!ini_get('date.timezone')) {
    ini_set('date.timezone', 'UTC');
}

/**
 * @brief The date/time class
 *
 * @detail This class can be used to manage date and time values and perform date arithmetic.
 *
 * ### Example
 *
 * ```php
 * $date = new Hazaar\Date('next tuesday');
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
class Date extends \DateTime implements \JsonSerializable, \DateTimeInterface
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
     * @detail The Date class constructor takes two values.
     * A datetime declaration and a timezone. Both are
     * optional. If now datetime is specified then the current datetime is used. If no timezone is
     * specified then the default timezone will be used.
     *
     * @param mixed                $datetime The datetime value you want to work with. This can be either an integer representing
     *                                       the datetime Epoch value (seconds since 1970-01-01) or a string datetime description supported by the
     *                                       PHP strtotime() function. This means that textual datetime descriptions such as 'now' and 'next
     *                                       monday' will work. See the PHP documentation on
     *                                       [[http://au1.php.net/manual/en/function.strtotime.php|strtotime()]] for more information on valid
     *                                       formats. If the datetime value is not set, the current date and time will be used.
     * @param \DateTimeZone|string $timezone The timezone for this datetime value. If no timezone is specified then the default
     *                                       timezone is used.
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
            } elseif (array_key_exists('usec', $datetime) && array_key_exists('date', $datetime)) { // Array version of Hazaar\Date
                if (!$timezone && array_key_exists('timezone', $datetime)) {
                    $timezone = $datetime['timezone'];
                }
                $datetime = '@'.strtotime($datetime['date']).'.'.$datetime['usec'];
            } else {
                $datetime = null;
            }
        } elseif ($datetime instanceof Date) {
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
        } else {
            $this->usec = 0;
            $year = 2000;
            $month = 5;
            $day = 4;
            $time = date_parse(str_ftime('%x', mktime(0, 0, 0, $month, $day, $year)));
            if ($time['month'] !== $month && preg_match('/\d+\/\d+\/\d+/', $datetime)) {
                $datetime = str_replace('/', '-', $datetime);
            }
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
        return $this->format($this->instanceFormat ? $this->instanceFormat : Date::$defaultFormat);
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
    public function setTimezone(null|\DateTimeZone|string $timezone = null): Date
    {
        if (null === $timezone) {
            $timezone = date_default_timezone_get();
        }
        if (is_numeric($timezone) && ($tz = ake(timezone_identifiers_list(), (int) $timezone))) {
            $timezone = $tz;
        }
        if (!$timezone instanceof \DateTimeZone) {
            if (is_numeric($timezone) && ($tz = timezone_name_from_abbr('', $timezone, -1))) {
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
        $format = Date::$dateFormat;

        return parent::format($format);
    }

    /**
     * Return the time part formatted by the global time format.
     *
     * @return string The time part of the datetime value
     */
    public function time()
    {
        $format = Date::$timeFormat;

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
        $format = Date::$dateFormat.' '.Date::$timeFormat;

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
        return (int) round(years(time() - $this->sec()), $precision);
    }

    /**
     * Returns the difference between the current date/time value and the value supplied as the first argument.
     *
     * Normally this method will return a \DateInterval object which is the default for the \DateTime class. However
     * this functionality has been extended with the $returnSeconds parameter which will instead return an integer
     * value indicating the difference in whole seconds.
     *
     * @param Date $timestamp The timestamp to compare the current date/time to
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
     * @param \DateInterval|string $interval   Can be either a \DateInterval object or a string representing an
     *                                         interval, such as P1H to specify 1 hour
     * @param bool                 $returnNew Doesn't update the current \Hazaar\Date object and instead returns
     *                                         a new object with the interval applied
     */
    public function add(\DateInterval|string $interval, $returnNew = false): static
    {
        if (!$interval instanceof \DateInterval) {
            $interval = new \DateInterval($interval);
        }
        if ($returnNew) {
            $new = new Date($this, $this->getTimezone());

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
     * @param \DateInterval|string $interval   can be either a \DateInterval object or a string representing an interval, such as P1H to specify 1 hour
     * @param bool                 $returnNew doesn't update the current \Hazaar\Date object and instead returns a new object with the interval applied
     */
    public function sub(\DateInterval|string $interval, bool $returnNew = false): static
    {
        if (!$interval instanceof \DateInterval) {
            $interval = new \DateInterval($interval);
        }
        if ($returnNew) {
            $new = new Date($this, $this->getTimezone());

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
        if (!$date instanceof Date) {
            $date = new Date($date, $this->getTimezone());
        }

        return $this->format($format) == $date->format($format);
    }

    /**
     * Returns the start time of the current date as a Date object.
     */
    public function start(): Date
    {
        return new Date($this->format('Y-m-d 00:00:00'), $this->getTimezone());
    }

    /**
     * Returns the end time of the current date as a Date object.
     */
    public function end(): Date
    {
        return new Date($this->format('Y-m-d 23:59:59'), $this->getTimezone());
    }

    /**
     * Returns the first day of the current week as a Date object.
     */
    public function firstOfWeek(): Date
    {
        return $this->sub('P'.($this->format('N') - 1).'D', true)->setTime(0, 0, 0);
    }

    /**
     * Returns the last day of the current week as a Date object.
     */
    public function lastOfWeek(): Date
    {
        return $this->add('P'.(7 - $this->format('N')).'D', true)->setTime(23, 59, 59);
    }

    /**
     * Returns the first day of the current month as a Date object.
     */
    public function firstOfMonth(): Date
    {
        return new Date($this->format('Y-m-01 00:00:00'), $this->getTimezone());
    }

    /**
     * Returns the first day of the current year as a Date object.
     */
    public function firstOfYear(): Date
    {
        return new Date($this->format('Y-01-01 00:00:00'), $this->getTimezone());
    }

    /**
     * Returns the last day of the current year as a Date object.
     */
    public function lastOfYear(): Date
    {
        return new Date($this->format('Y-12-31 23:59:59'), $this->getTimezone());
    }

    /**
     * Return a fuzzy diff between the current time and the Date value.
     *
     * @param bool $precise             Boolean indicating if precise mode should be used.  This generally adds the time
     *                                  to day-based results.
     * @param int  $dateThresholdDays A threshold in days after which the full date will be returned.  Avoids
     *                                  situations like "3213 days ago" which is silly.
     *
     * @return string returns a nice fuzzy interval like "yesterday at xx:xx" or "4 days ago"
     */
    public function fuzzy(bool $precise = false, int $dateThresholdDays = 30): string
    {
        $diff = $this->diff(new Date(null, $this->getTimezone()));
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
        if (preg_match('/(\d+)(\W)(\d+)(\W)(\d+)/', str_ftime('%c', mktime(0, 0, 0, 12, 1, 2000)), $matches)) {
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
        return $this->instanceFormat ? parent::format($this->instanceFormat) : $this->timestamp();
    }
}
