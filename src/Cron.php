<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Cron.php
 *
 * @author      Christian Land http://tagdocs.de
 * @author      Jamie Carl <jamie@hazaar.io>
 */

namespace Hazaar;

define('IDX_MINUTE', 0);
define('IDX_HOUR', 1);
define('IDX_DAY', 2);
define('IDX_MONTH', 3);
define('IDX_WEEKDAY', 4);
define('IDX_YEAR', 5);

/**
 * This class can be used to parse cron strings and compute schedules.
 *
 * It can parse a given string with a schedule specification in cron format.  The class can compute the last and the
 * next schedule times relative to a given time.
 *
 * ### Example
 *
 * ```php
 * $cron = new \Hazaar\Cron('0,30 9-17 \* \* 1-5');
 * $next = $cron->getNextOccurrence();
 * ```
 *
 * This will get the next occurrence from the schedule which should return dates and times for every 0th and 30th minute
 * between 9am and 5pm, Monday to Friday.
 *
 * @note    This code was originally added to Hazaar MVC in August of 2016 from code found randomly on the internet.  Probably
 *          StackOverflow?  It was never intended to be incorporated into the framework, but became a depdendency for the
 *          Hazaar Warlock library.  By this point and at that time I was unable to remember, or find, the original author
 *          and where I came across this code.
 *
 *          I have now (May 2019) done another search and found the original author is Christian Land who has since released
 *          this code under the MIT licence as the tdCron library.  He has made this library freely available on Github
 *          at https://github.com/chland/tdCron.
 *
 *          I would like to thank Christian for his initial work on this library as, after a few tweaks, it has become the
 *          integral scheduling code for Hazaar MVC/Warlock.
 *
 * @license https://github.com/chland/tdCron/blob/master/LICENSE.md MIT License
 */
class Cron
{
    /**
     * Ranges.
     *
     * @var array<mixed>
     */
    private $ranges = [
        IDX_MINUTE => ['min' => 0,
            'max' => 59,
            'name' => 'i'],    // Minutes
        IDX_HOUR => ['min' => 0,
            'max' => 23,
            'name' => 'G'],    // Hours
        IDX_DAY => ['min' => 1,
            'max' => 31,
            'name' => 'd'],    // Days
        IDX_MONTH => ['min' => 1,
            'max' => 12,
            'name' => 'm'],    // Months
        IDX_WEEKDAY => ['min' => 0,
            'max' => 7,
            'name' => 'w'],    // Weekdays
    ];

    /**
     * Named intervals.
     *
     * @var array<string,string>
     */
    private $intervals = [
        '@yearly' => '0 0 1 1 *',
        '@annualy' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@midnight' => '0 0 * * *',
        '@daily' => '0 0 * * *',
        '@hourly' => '0 * * * *',
        '@reboot' => 'now',
    ];

    /**
     * Possible keywords for months/weekdays.
     *
     * @var array<mixed>
     */
    private $keywords = [
        IDX_MONTH => [
            '/(january|januar|jan)/i' => 1,
            '/(february|februar|feb)/i' => 2,
            '/(march|maerz|m�rz|mar|mae|m�r)/i' => 3,
            '/(april|apr)/i' => 4,
            '/(may|mai)/i' => 5,
            '/(june|juni|jun)/i' => 6,
            '/(july|juli|jul)/i' => 7,
            '/(august|aug)/i' => 8,
            '/(september|sep)/i' => 9,
            '/(october|oktober|okt|oct)/i' => 10,
            '/(november|nov)/i' => 11,
            '/(december|dezember|dec|dez)/i' => 12,
        ],
        IDX_WEEKDAY => [
            '/(sunday|sonntag|sun|son|su|so)/i' => 0,
            '/(monday|montag|mon|mo)/i' => 1,
            '/(tuesday|dienstag|die|tue|tu|di)/i' => 2,
            '/(wednesdays|mittwoch|mit|wed|we|mi)/i' => 3,
            '/(thursday|donnerstag|don|thu|th|do)/i' => 4,
            '/(friday|freitag|fre|fri|fr)/i' => 5,
            '/(saturday|samstag|sam|sat|sa)/i' => 6,
        ],
    ];

    /**
     * @var array<int> The parsed CRON expression
     */
    private array|false|int $pcron;

    /**
     * Creates a new instance of the Cron class.
     *
     * @param int|string $expression The CRON expression to parse
     */
    public function __construct(int|string $expression)
    {
        $this->pcron = is_int($expression) ? $expression : $this->parse($expression);
        if (false === $this->pcron) {
            throw new Exception('Invalid CRON time expression: '.$expression);
        }
    }

    /**
     * Calculates the next time and date based on the supplied expression.
     *
     * If a reference-time is passed, the next time and date after that time is calculated.
     *
     * @param int $timestamp optional reference-time
     */
    public function getNextOccurrence(?int $timestamp = null): ?int
    {
        if (!$this->pcron) {
            return null;
        }
        $next = $this->getTimestamp($timestamp);
        ++$next[IDX_MINUTE];

        return $this->calculateDateTime($next);
    }

    /**
     * Calculates the last time and date before the supplied expression.
     *
     * If a reference-time is passed, the last time and date before that time is calculated.
     *
     * @param int $timestamp optional reference-time
     */
    public function getLastOccurrence(?int $timestamp = null): ?int
    {
        if (!$this->pcron) {
            return null;
        }
        // Convert timestamp to array
        $last = $this->getTimestamp($timestamp);

        // Calculate date/time
        return $this->calculateDateTime($last, false);
        // return calculated time
    }

    /**
     * Calculates the time and date at which the next/last call of a cronjob is/was due.
     *
     * @param array<int> $rtime reference-time
     * @param bool       $next  true = nextOccurence, false = lastOccurence
     */
    private function calculateDateTime(array $rtime, bool $next = true): ?int
    {
        if (is_int($this->pcron)) {
            $timestamp = mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);

            return (true === $next) ? (($this->pcron >= $timestamp) ? $this->pcron : null) : (($this->pcron < $timestamp) ? $this->pcron : null);
        }
        // Initialize vars
        $calcDate = true;
        $cron = ($next ? $this->pcron : $this->arrayReverse($this->pcron));
        if (!$cron) {
            return null;
        }
        // OK, lets see if the day/month/weekday of the reference-date exist in our
        // $cron-array.
        if (!in_array($rtime[IDX_DAY], $cron[IDX_DAY])
            || !in_array($rtime[IDX_MONTH], $cron[IDX_MONTH])
            || !in_array($rtime[IDX_WEEKDAY], $cron[IDX_WEEKDAY])
        ) {
            // OK, things are easy. The day/month/weekday of the reference time
            // can't be found in the $cron-array. This means that no matter what
            // happens, we WILL end up at at a different date than that of our
            // reference-time. And in this case, the lastOccurrence will ALWAYS
            // happen at the latest possible time of the day and the nextOccurrence
            // at the earliest possible time.
            //
            // In both cases, the time can be found in the first elements of the
            // hour/minute cron-arrays.
            $rtime[IDX_HOUR] = reset($cron[IDX_HOUR]);
            $rtime[IDX_MINUTE] = reset($cron[IDX_MINUTE]);
        } else {
            // OK, things are getting a little bit more complicated...
            $nhour = $this->findValue($rtime[IDX_HOUR], $cron[IDX_HOUR], $next);
            // Meh. Such a cruel world. Something has gone awry. Lets see HOW awry it went.
            if (false === $nhour) {
                // Ah, the hour-part went wrong. Thats easy. Wrong hour means that no
                // matter what we do we'll end up at a different date. Thus we can use
                // some simple operations to make things look pretty ;-)
                //
                // As alreasy mentioned before -> different date means earliest/latest
                // time:
                $rtime[IDX_HOUR] = reset($cron[IDX_HOUR]);
                $rtime[IDX_MINUTE] = reset($cron[IDX_MINUTE]);
                // Now all we have to do is add/subtract a day to get a new reference time
                // to use later to find the right date. The following line probably looks
                // a little odd but thats the easiest way of adding/substracting a day without
                // screwing up the date. Just trust me on that one ;-)
                $rtime = explode(',', str_ftime('%M,%H,%d,%m,%w,%Y', mktime($rtime[IDX_HOUR], $rtime[IDX_MINUTE], 0, $rtime[IDX_MONTH], $rtime[IDX_DAY], $rtime[IDX_YEAR]) + ((($next) ? 1 : -1) * 86400)));
            } else {
                // OK, there is a higher/lower hour available. Check the minutes-part.
                $nminute = $this->findValue($rtime[IDX_MINUTE], $cron[IDX_MINUTE], $next);
                if (false === $nminute) {
                    // No matching minute-value found... lets see what happens if we substract/add an hour
                    $nhour = $this->findValue($rtime[IDX_HOUR] + (($next) ? 1 : -1), $cron[IDX_HOUR], $next);
                    if (false === $nhour) {
                        // No more hours available... add/substract a day... you know what happens ;-)
                        $nminute = reset($cron[IDX_MINUTE]);
                        $nhour = reset($cron[IDX_HOUR]);
                        $rtime = explode(',', str_ftime('%M,%H,%d,%m,%w,%Y', mktime($nhour, $nminute, 0, $rtime[IDX_MONTH], $rtime[IDX_DAY], $rtime[IDX_YEAR]) + ((($next) ? 1 : -1) * 86400)));
                    } else {
                        // OK, there was another hour. Set the right minutes-value
                        $rtime[IDX_HOUR] = $nhour;
                        $rtime[IDX_MINUTE] = (($next) ? reset($cron[IDX_MINUTE]) : end($cron[IDX_MINUTE]));
                        $calcDate = false;
                    }
                } else {
                    // OK, there is a matching minute... reset minutes if hour has changed
                    if ($nhour != $rtime[IDX_HOUR]) {
                        $nminute = reset($cron[IDX_MINUTE]);
                    }
                    // Set time
                    $rtime[IDX_HOUR] = $nhour;
                    $rtime[IDX_MINUTE] = $nminute;
                    $calcDate = false;
                }
            }
        }
        // If we have to calculate the date... we'll do so
        if ($calcDate) {
            if (in_array($rtime[IDX_DAY], $cron[IDX_DAY]) && in_array($rtime[IDX_MONTH], $cron[IDX_MONTH]) && in_array($rtime[IDX_WEEKDAY], $cron[IDX_WEEKDAY])) {
                return mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);
            }
            // OK, some searching necessary...
            $cdate = mktime(0, 0, 0, $rtime[IDX_MONTH], $rtime[IDX_DAY], $rtime[IDX_YEAR]);
            // OK, these three nested loops are responsible for finding the date...
            //
            // The class has 2 limitations/bugs right now:
            //
            //	-> it doesn't work for dates in 2036 or later!
            //	-> it will most likely fail if you search for a Feburary, 29th with a given weekday
            //	   (this does happen because the class only searches in the next/last 10 years! And
            //	   while it usually takes less than 10 years for a "normal" date to iterate through
            //	   all weekdays, it can take 20+ years for Feb, 29th to iterate through all weekdays!
            for ($nyear = $rtime[IDX_YEAR]; ($next) ? ($nyear <= $rtime[IDX_YEAR] + 10) : ($nyear >= $rtime[IDX_YEAR] - 10); $nyear = $nyear + (($next) ? 1 : -1)) {
                foreach ($cron[IDX_MONTH] as $nmonth) {
                    foreach ($cron[IDX_DAY] as $nday) {
                        if (checkdate($nmonth, $nday, $nyear)) {
                            $ndate = mktime(0, 0, 1, $nmonth, $nday, $nyear);
                            if (($next) ? ($ndate >= $cdate) : ($ndate <= $cdate)) {
                                $dow = date('w', $ndate);
                                // The date is "OK" - lets see if the weekday matches, too...
                                if (in_array($dow, $cron[IDX_WEEKDAY])) {
                                    // WIN! :-) We found a valid date...
                                    $rtime = explode(',', str_ftime('%M,%H,%d,%m,%w,%Y', mktime($rtime[IDX_HOUR], $rtime[IDX_MINUTE], 0, $nmonth, $nday, $nyear)));

                                    return mktime(
                                        (int) $rtime[1],
                                        (int) $rtime[0],
                                        0,
                                        (int) $rtime[3],
                                        (int) $rtime[2],
                                        (int) $rtime[5]
                                    );
                                }
                            }
                        }
                    }
                }
            }

            return null;
        }

        return mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);
    }

    /**
     * Converts an unix-timestamp to an array.
     *
     * The returned array contains the following values:
     *
     *    [0]    -> minute
     *    [1]    -> hour
     *    [2]    -> day
     *    [3]    -> month
     *    [4]    -> weekday
     *    [5]    -> year
     *
     * The array is used by various functions.
     *
     * @param int $timestamp If none is given, the current time is used
     *
     * @return array<mixed>
     */
    private function getTimestamp(?int $timestamp = null): array
    {
        if (is_null($timestamp)) {
            $timestamp = time();
        }
        $arr = explode(',', str_ftime('%M,%H,%d,%m,%w,%Y', $timestamp));
        // Remove leading zeros (or we'll get in trouble ;-)
        array_walk($arr, function (&$value) { $value = (int) $value; });

        return $arr;
    }

    /**
     * Checks if the given value exists in an array.
     *
     * If it does not exist, the next higher/lower value is returned (depending on $next). If no higher/lower value
     * exists, false is returned.
     *
     * @param int   $value
     * @param mixed $data
     * @param bool  $next
     *
     * @return mixed the next value or false if there isn't one
     */
    private function findValue($value, $data, $next = true)
    {
        if (in_array($value, $data)) {
            return (int) $value;
        }
        if (($next) ? ($value <= end($data)) : ($value >= end($data))) {
            foreach ($data as $curval) {
                if (($next) ? ($value <= (int) $curval) : ($curval <= $value)) {
                    return (int) $curval;
                }
            }
        }

        return false;
    }

    /**
     * Reverses all sub-arrays of our cron array.
     *
     * The reversed values are used for calculations that are run when getLastOccurence() is called.
     *
     * @param array<mixed> $cron
     *
     * @return array<mixed>
     */
    private function arrayReverse(array $cron): array
    {
        foreach ($cron as $key => $value) {
            $cron[$key] = array_reverse($value);
        }

        return $cron;
    }

    /**
     * Analyses crontab-expressions like "* * 1,2,3 * mon,tue" and returns an array containing all values.
     *
     * If it can not be parsed then it returns FALSE
     *
     * @param string $expression the cron-expression to parse
     */
    private function parse(string $expression): mixed
    {
        // First of all we cleanup the expression and remove all duplicate tabs/spaces/etc.
        $expression = preg_replace('/(\s+)/', ' ', strtolower(trim($expression)));
        // Convert named expressions if neccessary
        if ('@' == substr($expression, 0, 1)) {
            $expression = strtr($expression, $this->intervals);
            if ('@' == substr($expression, 0, 1)) {
                return false;
            }
        }
        if ('now' === $expression) {
            return (int) (floor(time() / 60) * 60);
        }
        // Next basic check... do we have 5 segments?
        $cron = explode(' ', $expression);
        if (5 !== count($cron)) {
            return false;
        }
        $dummy = [];
        // Yup, 5 segments... lets see if we can work with them
        foreach ($cron as $idx => $segment) {
            if (($value = $this->expandSegment($idx, $segment)) === false) {
                return false;
            }
            $dummy[$idx] = $value;
        }

        return $dummy;
    }

    /**
     * Analyses a single segment.
     *
     * @return array<mixed>
     */
    private function expandSegment(int $idx, string $segment): array|false
    {
        // Replace months/weekdays like "January", "February", etc. with numbers
        if (isset($this->keywords[$idx])) {
            $segment = preg_replace(
                array_keys($this->keywords[$idx]),
                array_values($this->keywords[$idx]),
                $segment
            );
        }
        // Replace wildcards
        $token = substr($segment, 0, 1);
        if ('*' === $token) {
            $segment = preg_replace('/^\*(\/\d+)?$/i', $this->ranges[$idx]['min'].'-'.$this->ranges[$idx]['max'].'$1', $segment);
        } elseif ('?' === $token) {
            $segment = preg_replace('/^\?(\/\d+)?$/i', date($this->ranges[$idx]['name']).'$1', $segment);
        }
        // Make sure that nothing unparsed is left :)
        $dummy = preg_replace('/[0-9\-\/\,]/', '', $segment);
        if (!empty($dummy)) {
            return false;
        }
        // At this point our string should be OK - lets convert it to an array
        $result = [];
        $atoms = explode(',', $segment);
        foreach ($atoms as $curatom) {
            $result = array_merge($result, $this->parseAtom($curatom));
        }
        // Get rid of duplicates and sort the array
        $result = array_unique($result);
        sort($result);
        // Check for invalid values
        if (IDX_WEEKDAY == $idx) {
            if (7 == end($result)) {
                if (0 != reset($result)) {
                    array_unshift($result, 0);
                }
                array_pop($result);
            }
        }
        foreach ($result as $key => $value) {
            if (($value < $this->ranges[$idx]['min']) || ($value > $this->ranges[$idx]['max'])) {
                return false;
            }
        }

        return $result;
    }

    /**
     * Analyses a single segment.
     *
     * @param string $atom The segment to parse
     *
     * @return array<mixed>
     */
    private function parseAtom(string $atom): array
    {
        $expanded = [];
        if (preg_match('/^(\d+)-(\d+)(\/(\d+))?/i', $atom, $matches)) {
            $low = $matches[1];
            $high = $matches[2];
            if ($low > $high) {
                list($low, $high) = [$high, $low];
            }
            $step = isset($matches[4]) ? $matches[4] : 1;
            for ($i = $low; $i <= $high; $i += $step) {
                $expanded[] = (int) $i;
            }
        } else {
            $expanded[] = (int) $atom;
        }

        return $expanded;
    }
}
