<?php

declare(strict_types=1);

use Hazaar\Application;
use Hazaar\Application\Request;
use Hazaar\Controller\Dump;

$dumpLog = [];

/**
 * Array/Object value normalizer.
 *
 * Returns a value from an array or a property from an object, if it exists. If it doesn't exist a default
 * value can be specified.  Otherwise null is returned.
 *
 * This helps prevent array key not found errors in the PHP interpreter.
 *
 * Keys may be specified using dot-notation.  This allows ake to be called only once instead of for each
 * element in a reference chain.  For example, you can call `ake($myarray, 'object.child.other');` and each
 * reference will be recursed into if it exists.  If at any step the child does not exist (or is empty if
 * `$non_empty === TRUE`) then execution will stop and return the default value.  This will also handle things
 * if the child is not an array or object.
 *
 * If the key contains round or square brackets, then this is taken as a search parameter, allowing the specified
 * element to be search for child elements that match the search criteria.  This search parameter can, and is
 * actually designed to, be used with dot-notation.  So for example, you can call `ake($myarray, 'items(type.id=1).name')`
 * to find an element in the `items` sub-element of `$myarray` that has it's own `type` element with another
 * sub-element of `id` with a value that matches `1`.  As you can imagine, this allows quite a power way of accessing
 * sub-elements of arrays/objects using a simple dot-notation search parameter.
 *
 * The `$key` parameter can also be an array of keys.  In this case, the array will be searched for each key
 * and the first value found will be returned.  This is handy if you need a value that could be stored under
 * multiple possible key names.
 *
 * @param mixed $array     The array to search.  Objects with public properties are also supported.
 * @param mixed $key       The array key or object property name to look for.  The following key specifications
 *                         are supported:
 *                         * _string_ - Single key.
 *                         * _string_ - Dot notation key for decending into multi-dimensional arrays/objects.
 *                         * _array_  - Array of keys to search for where the first found is returned.
 * @param mixed $default   An optional default value to return if the key or property does not exist.
 *                         This default can also be a callback function (closure).  If so, the default
 *                         value will be the value returned by the closure.  Usefull of using objects as defaults.
 * @param bool  $non_empty indicates that empty values, such as empty arrays and strings should be
 *                         treated as NULL, even if they exist as elements in the array/object
 *
 * @return mixed The value if it exists in the array. Returns the default if it does not. Default is null
 *               if no other default is specified.
 *
 * @deprecated deprecated in favour of PHP native ?? operator
 */
function ake(mixed $array, mixed $key, mixed $default = null, bool $non_empty = false): mixed
{
    if (is_string($key) || is_int($key)) {
        if ((is_array($array) || $array instanceof ArrayAccess)
            && isset($array[$key])
            && (false === $non_empty || (is_string($array[$key]) ? trim($array[$key]) : $array[$key]))) {
            return $array[$key];
        }
        if (is_object($array)) {
            if (isset($array->{$key})
                && (
                    false === $non_empty
                    || false === is_string($array->{$key})
                    || '' !== trim($array->{$key})
                )
            ) {
                return $array->{$key};
            }
            if ($array instanceof ArrayAccess && isset($array[$key])) {
                return $array[$key];
            }
        }
        if (!is_int($key) && (false !== strpos($key, '.') || false !== strpos($key, '(') || false !== strpos($key, '['))) {
            $parts = preg_split('/\.(?![^([]*[\)\]])/', $key);
            foreach ($parts as $part) {
                if (preg_match('/^(\w+)([\(\[])([\w\d\.=\s"\']+)[\)\]]$/', $part, $matches)) {
                    if (!(($array = ake($array, $matches[1], $default, $non_empty))
                        && (is_array($array) || $array instanceof stdClass || $array instanceof ArrayAccess))
                        || $array === $default) {
                        break;
                    }
                    if (false === strpos($matches[3], '=')) {
                        $item = is_numeric($matches[3]) ? (int) $matches[3] : $matches[3];
                        if (!array_key_exists($item, $array)) {
                            break;
                        }
                        $array = $array[$item];
                    } else {
                        [$item, $criteria] = explode('=', $matches[3]);
                        if (('"' === $criteria[0] || "'" === $criteria[0]) && $criteria[0] === substr($criteria, -1)) {
                            $criteria = trim($criteria, '"\'');
                        } elseif (strpos($criteria, '.')) {
                            $criteria = floatval($criteria);
                        } elseif (is_numeric($criteria)) {
                            $criteria = (int) $criteria;
                        }
                        foreach ($array as $elem) {
                            if (ake($elem, $item) === $criteria) {
                                $array = $elem;

                                break;
                            }
                        }
                    }
                } elseif (($array = ake($array, $part, $default, $non_empty)) === $default) {
                    break;
                }
            }

            return $array;
        }
    } elseif (is_array($key)) {
        foreach ($key as $k) {
            if ($value = ake($array, $k, null, $non_empty)) {
                return $value;
            }
        }
    }

    return is_callable($default) ? $default($key) : $default;
}

/**
 * Normalize boolean values.
 *
 * This helper function will take a string representation of a boolean such as 't', 'true', 'yes', 'ok' and
 * return a boolean type value.
 *
 * @param mixed $value The string representation of the boolean
 */
function boolify(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    $value = strbool($value);
    if ('true' == $value) {
        return true;
    }

    return false;
}

/**
 * Retrieve string value of boolean.
 *
 * Normalise boolean string to 'true' or 'false' based on various boolean representations
 *
 * @param mixed $value The string representation of the boolean
 */
function strbool(mixed $value): string
{
    if (false === $value || is_array($value) || null === $value) {
        return 'false';
    }
    if (true === $value) {
        return 'true';
    }
    if (is_string($value)) {
        $value = strtolower(trim($value));
        if ('t' == $value
            || 'true' == $value
            || 'on' == $value
            || 'yes' == $value
            || 'y' == $value
            || 'ok' == $value
            || '1' == $value) {
            return 'true';
        }
        if (preg_match('/(\!|not)\s*null/', $value)) {
            return 'true';
        }
    } elseif (is_int($value)) {
        if (0 != (int) $value) {
            return 'true';
        }
    }

    return 'false';
}

/**
 * Test whether a value is a boolean.
 *
 * Checks for various representations of a boolean, including strings of 'true/false' and 'yes/no'.
 *
 * @return bool
 */
function is_boolean(mixed $value)
{
    if (!is_string($value)) {
        return is_bool($value);
    }
    $accepted = [
        't',
        'true',
        'f',
        'false',
        'y',
        'yes',
        'n',
        'no',
        'on',
        'off',
    ];

    return in_array(strtolower(trim($value)), $accepted);
}

/**
 * The Yes/No function.
 *
 * Simply returns Yes or No based on a boolean value.
 */
function yn(mixed $value, string $trueValue = 'Yes', string $falseValue = 'No'): string
{
    return boolify($value) ? $trueValue : $falseValue;
}

/**
 * Encodes data to a Base64 URL-safe string.
 *
 * This function encodes the given data using Base64 encoding and then makes the
 * encoded string URL-safe by replacing '+' with '-' and '/' with '_'. It also
 * removes any trailing '=' characters.
 *
 * @param string $data the data to be encoded
 *
 * @return string the Base64 URL-safe encoded string
 */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Decodes a base64 URL encoded string.
 *
 * This function takes a base64 URL encoded string and decodes it back to its original form.
 * It replaces URL-safe characters ('-' and '_') with standard base64 characters ('+' and '/'),
 * and pads the string with '=' characters to ensure its length is a multiple of 4.
 *
 * @param string $data the base64 URL encoded string to decode
 *
 * @return string the decoded string
 */
function base64url_decode(string $data): string
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - (strlen($data) % 4) % 4), '=', STR_PAD_RIGHT));
}

/**
 * Build a correctly formatted URL from argument list.
 *
 * This function will build a correctly formatted HTTP compliant URL using a list of parameters. If any
 * of the parameters are null then they will be omitted from the formatted output, including any extra values.
 *
 * For example, you can specify a username and a password which will be formatted as username:password\@.  However
 * if you omit the password you will simply get username\@.
 *
 * @param string       $scheme   The protocol to use. Usually http or https.
 * @param string       $host     Hostname
 * @param int          $port     (optional) Port
 * @param string       $user     (optional) Username
 * @param string       $pass     (optional) User password. If set, a username is required.
 * @param string       $path     (optional) Path suffix
 * @param array<mixed> $query    (optional) Array of parameters to send. ie: the stuff after the '?'. Uses http_build_query to generate string.
 * @param string       $fragment (optional) Anything to go after the '#'
 */
function build_url(
    string $scheme = 'http',
    string $host = 'localhost',
    ?int $port = null,
    ?string $user = null,
    ?string $pass = null,
    ?string $path = null,
    array $query = [],
    ?string $fragment = null
): string {
    $url = strtolower(trim($scheme)).'://';
    if ($user = trim($user) || ($user && $pass = trim($pass))) {
        $url .= $user.($pass ? ':'.$pass : null).'@';
    }
    $url .= trim($host);
    if (80 != $port) {
        $url .= ':'.$port;
    }
    if ($path = trim($path)) {
        $url .= $path;
    }
    if (count($query) > 0) {
        $url .= '?'.http_build_query($query);
    }
    if ($fragment = trim($fragment)) {
        $url .= '#'.$fragment;
    }

    return $url;
}

/**
 * Byte to string formatter.
 *
 * Formats an integer representing a size in bytes to a human readable string representation.
 *
 * @param int    $bytes          the byte value to convert to a string
 * @param string $type           The type to convert to. Type can be:
 *                               * B (bytes)
 *                               * K (kilobytes)
 *                               * M (megabytes)
 *                               * G (giabytes)
 *                               * T (terrabytes)
 * @param int    $precision      the number of decimal places to show
 * @param bool   $exclude_suffix if true, the suffix will not be included in the output
 *
 * @return string The human readable byte string. eg: '100 MB'.
 */
function str_bytes(int $bytes, ?string $type = null, ?int $precision = null, bool $exclude_suffix = false)
{
    if (null === $type) {
        if ($bytes < pow(2, 10)) {
            $type = 'B';
        } elseif ($bytes < pow(2, 20)) {
            $type = 'K';
        } elseif ($bytes < pow(2, 30)) {
            $type = 'M';
        } else {
            $type = 'G';
        }
    }
    $type = strtoupper($type);
    $value = $bytes;
    $suffix = 'bytes';
    $prec = 0;

    switch ($type) {
        case 'K':
            $value = $bytes / pow(2, 10);
            $suffix = 'KB';

            break;

        case 'M':
            $value = $bytes / pow(2, 20);
            $suffix = 'MB';
            $prec = 2;

            break;

        case 'G':
            $value = $bytes / pow(2, 30);
            $suffix = 'GB';
            $prec = 2;

            break;

        case 'T':
            $value = $bytes / pow(2, 40);
            $suffix = 'TB';
            $prec = 2;

            break;
    }
    if (null !== $precision) {
        $prec = $precision;
    }

    return number_format($value, $prec).($exclude_suffix ? '' : ' '.$suffix);
}

/**
 * String to bytes formatter.
 *
 * Returns an integer value representing a number of bytes from the standard bytes size string supplied.
 *
 * @param string $string The byte string value to convert to an integer. eg: '100MB'
 *
 * @return bool|float The number of bytes or false on failure
 */
function bytes_str(string $string): bool|float
{
    if (preg_match('/([\d\.]+)\s*(\w*)/', $string, $matches)) {
        $size = floatval($matches[1]);

        switch (strtoupper($matches[2])) {
            case 'K':
            case 'KB':
                $size = round($size * pow(2, 10));

                break;

            case 'M':
            case 'MB':
                $size = round($size * pow(2, 20));

                break;

            case 'G':
            case 'GB':
                $size = round($size * pow(2, 30));

                break;

            case 'T':
            case 'TB':
                $size = round($size * pow(2, 40));

                break;
        }

        return $size;
    }

    return false;
}

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
function seconds(string $interval): int
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
function minutes(int $interval): float
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
function hours(int $interval): float
{
    return minutes($interval) / 60;
}

/**
 * Convert interval to days.
 *
 * Same as the seconds function except returns the number of days.
 *
 * @return float Days in interval
 */
function days(int $interval): float
{
    return hours($interval) / 24;
}

/**
 * Convert interval to weeks.
 *
 * Same as the seconds function except returns the number of weeks.
 *
 * @return float Weeks in interval
 */
function weeks(int $interval): float
{
    return days($interval) / 7;
}

/**
 * Convert interval to years.
 *
 * Same as the seconds function except returns the number of years.
 *
 * @return float Years in interval
 */
function years(int $interval): float
{
    return days($interval) / 365.25;
}

/**
 * Get the age of a date.
 *
 * This helper function will return the number of years between a specified date and now. Useful for
 * getting an age.
 *
 * @return int number of years from the specified date to now
 */
function age(DateTime|int|string $date): int
{
    if ($date instanceof DateTime) {
        $time = $date->getTimestamp();
    } elseif (is_string($date)) {
        $time = strtotime($date);
    } else {
        $time = $date;
    }

    return (int) floor(years(time() - $time));
}

/**
 * Convert interval to uptime string.
 *
 * This function will convert an integer of seconds into an uptime string similar to what is returned by
 * the unix uptime command. ie: '9 days 3:32:02'
 */
function uptime(int $interval): string
{
    $d = floor(days((int) $interval));
    $h = (string) (floor(hours((int) $interval)) - ($d * 24));
    $m = (string) (floor(minutes((int) $interval)) - (($h + ($d * 24)) * 60));
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

/**
 * Return a string interval in a nice readable format.
 *
 * Similar to uptime() this extends the format into a complete string in a nice, friendly readable format.
 *
 * @param mixed $seconds the interval to convert in seconds
 *
 * @return string a friendly string
 */
function interval(mixed $seconds): string
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
 * Fix a numeric string.
 *
 * Sometimes a numeric (int or float) will be stored as a string variable. This can cause
 * issues with functions that check the variable type to determine what to do with it. This
 * function allows you to simply pass a variable through it and it will 'fix' it. If it is
 * a string and is a numeric value, it will be converted to the appropriate variable type.
 *
 * If the value is a string and is meant to be a string, it will be left alone.
 *
 * @param mixed $value the variable to type check and possibly fix
 *
 * @return float|int the fixed variable
 */
function str_fixtype(&$value): float|int
{
    if (is_numeric($value)) {
        /*
         * NOTE: We can't use settype() here as it is unable to distinguish between a float and an int. So we
         * use this trick to force PHP to figure it out for itself. Adding zero will not affect the value
         * but will cause the type to be converted to int or float.
         */
        $value = $value + 0;
    }

    return $value;
}

/**
 * Helper function to get the status text for an HTTP response code.
 *
 * @param int $code the response code
 *
 * @return mixed A string containing the response text if the code is valid. False otherwise.
 */
function http_response_text($code)
{
    $data_file = dirname(__FILE__)
    .DIRECTORY_SEPARATOR.'..'
    .DIRECTORY_SEPARATOR.'libs'
    .DIRECTORY_SEPARATOR.'HTTP_Status.dat';
    if (!file_exists($data_file)) {
        throw new Exception('HTTP status data file is missing!');
    }
    $text = false;
    if (preg_match('/^'.$code.'\s(.*)$/m', file_get_contents($data_file), $matches)) {
        $text = $matches[1];
    }

    return $text;
}

/**
 * Get the current request headers.
 *
 * This function will return the current request headers as an associative array.
 *
 * @return array<string,string> The request headers
 */
function hazaar_request_headers(): array
{
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if ('HTTP_' == substr($name, 0, 5)) {
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
        }
    }
    // Fix a missing Content-Type header
    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }
    // Fix a missing Content-Length header
    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['Content-Length'] = (int) $_SERVER['CONTENT_LENGTH'];
    }

    return $headers;
}

function guid(): string
{
    $format = 'xxxxxxxx-yxxx-yxxx-yxxx-xxxxxxxxxxxx';

    return preg_replace_callback('/[xy]/', function ($c) {
        $num = rand(0, 15);
        $value = (('x' == $c[0]) ? $num : ($num & 0x3 | 0x8));

        return dechex($value);
    }, $format);
}

function log_dump(mixed $data): void
{
    global $dumpLog;
    $dumpLog[] = ['time' => microtime(true), 'data' => $data];
}

function dump(mixed ...$data): void
{
    global $dumpLog;
    $caller = [];
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (count($trace) > 0) {
        $caller['file'] = $trace[0]['file'] ?? '';
        $caller['line'] = $trace[0]['line'] ?? '';
    }
    if (count($trace) > 1) {
        $caller['function'] = $trace[1]['function'] ?? '';
        $caller['class'] = $trace[1]['class'] ?? '';
    }
    if (defined('HAZAAR_VERSION') && ($app = Application::getInstance())) {
        if (isset($app->router)) {
            $controller = new Dump($data);
            if (is_array($dumpLog)) {
                $controller->addLogEntries($dumpLog);
            }
            $request = new Request();
            $controller->initialize($request);
            $controller->setCaller($caller);
            $response = $controller->run();
            $response->writeOutput();
        } else {
            foreach ($data as $dataItem) {
                var_dump($dataItem);
            }
        }
    } else {
        $out = "HAZAAR DUMP\n\n";
        if (defined('HAZAAR_START')) {
            $exec_time = round((microtime(true) - HAZAAR_START) * 1000, 2);
            $out .= "Exec time: {$exec_time}\n";
        }
        $out .= 'Endtime: '.date('c')."\n\n";
        foreach ($data as $item) {
            $out .= print_r($item, true)."\n\n";
        }
        if (is_array($dumpLog) && count($dumpLog) > 0) {
            $out .= "\n\nLOG\n\n";
            $out .= print_r($dumpLog, true);
        }
        $out .= "BACKTRACE\n\n";
        $e = new Exception('Backtrace');
        $out .= print_r(str_replace('/path/to/code/', '', $e->getTraceAsString()), true)."\n";

        echo $out;
    }

    exit;
}

/**
 * Perform a regular expression match on a string using multiple possible regular expressions.
 *
 * This is the same as calling preg_match() except that $patterns is an array of regular expressions.
 * Execution returns on the first match.
 *
 * @param array<string>|string $patterns an array of patterns to search for, as a string
 * @param string               $subject  the input string
 * @param array<mixed>         $matches  If matches is provided, then it is filled with the results of search. $matches[0] will contain the text that matched the full pattern, $matches[1] will have the text that matched the first captured parenthesized subpattern, and so on.
 * @param mixed                $flags    For details on available flags, see the [preg_match()](http://php.net/manual/en/function.preg-match.php) documentation.
 * @param int                  $offset   Normally, the search starts from the beginning of the subject string. The optional parameter offset can be used to specify the alternate place from which to start the search (in bytes).
 */
function preg_match_array(
    array|string $patterns,
    string $subject,
    ?array &$matches = null,
    mixed $flags = '0',
    int $offset = 0
): int {
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $subject, $matches, $flags, $offset)) {
            return 1;
        }
    }

    return 0;
}

if (!function_exists('money_format')) {
    /**
     * Replacement for the "built-in" PHP function money_format(), which isn't always built-in.
     *
     * Some systems, particularly Windows (also possible BSD) the built-in money_format() function
     * will not be defined.  This is because it's just a wrapper for a C function called strfmon()
     * which isn't available on all platforms (such as Windows).
     *
     * @param string    $format The format specification, see
     * @param float|int $number the number to be formatted
     *
     * @return string the formatted number
     */
    function money_format(string $format, float|int $number): string
    {
        $regex = '/%((?:[\^!\-]|\+|\(|\=.)*)([0-9]+)?(?:#([0-9]+))?(?:\.([0-9]+))?([in%])/';
        if ('C' == setlocale(LC_MONETARY, '')) {
            setlocale(LC_MONETARY, '');
        }
        $locale = localeconv();
        preg_match_all($regex, $format, $matches, PREG_SET_ORDER);
        foreach ($matches as $fmatch) {
            $value = floatval($number);
            $flags = [
                'fillchar' => preg_match('/\=(.)/', $fmatch[1], $match) ?
                               $match[1] : ' ',
                'nogroup' => preg_match('/\^/', $fmatch[1]) > 0,
                'usesignal' => preg_match('/\+|\(/', $fmatch[1], $match) ?
                               $match[0] : '+',
                'nosimbol' => preg_match('/\!/', $fmatch[1]) > 0,
                'isleft' => preg_match('/\-/', $fmatch[1]) > 0,
            ];
            $width = trim($fmatch[2]) ? (int) $fmatch[2] : 0;
            $left = trim($fmatch[3]) ? (int) $fmatch[3] : 0;
            $right = trim($fmatch[4]) ? (int) $fmatch[4] : $locale['int_frac_digits'];
            $conversion = $fmatch[5];
            $positive = true;
            if ($value < 0) {
                $positive = false;
                $value *= -1;
            }
            $letter = $positive ? 'p' : 'n';
            $prefix = $suffix = $cprefix = $csuffix = $signal = '';
            $signal = $positive ? $locale['positive_sign'] : $locale['negative_sign'];

            switch (true) {
                case 1 == $locale["{$letter}_sign_posn"] && '+' == $flags['usesignal']:
                    $prefix = $signal;

                    break;

                case 2 == $locale["{$letter}_sign_posn"] && '+' == $flags['usesignal']:
                    $suffix = $signal;

                    break;

                case 3 == $locale["{$letter}_sign_posn"] && '+' == $flags['usesignal']:
                    $cprefix = $signal;

                    break;

                case 4 == $locale["{$letter}_sign_posn"] && '+' == $flags['usesignal']:
                    $csuffix = $signal;

                    break;

                case '(' == $flags['usesignal']:
                case 0 == $locale["{$letter}_sign_posn"]:
                    $prefix = '(';
                    $suffix = ')';

                    break;
            }
            if (!$flags['nosimbol']) {
                $currency = $cprefix.('i' == $conversion ? $locale['int_curr_symbol'] : $locale['currency_symbol']).$csuffix;
            } else {
                $currency = '';
            }
            $space = $locale["{$letter}_sep_by_space"] ? ' ' : '';
            $value = number_format($value, $right, $locale['mon_decimal_point'], $flags['nogroup'] ? '' : $locale['mon_thousands_sep']);
            $value = @explode($locale['mon_decimal_point'], $value);
            $n = strlen($prefix) + strlen($currency) + strlen($value[0]);
            if ($left > 0 && $left > $n) {
                $value[0] = str_repeat($flags['fillchar'], $left - $n).$value[0];
            }
            $value = implode($locale['mon_decimal_point'], $value);
            if ($locale["{$letter}_cs_precedes"]) {
                $value = $prefix.$currency.$space.$value.$suffix;
            } else {
                $value = $prefix.$value.$space.$currency.$suffix;
            }
            if ($width > 0) {
                $value = str_pad($value, $width, $flags['fillchar'], $flags['isleft'] ? STR_PAD_RIGHT : STR_PAD_LEFT);
            }
            $format = str_replace($fmatch[0], $value, $format);
        }

        return $format;
    }
}

if (!function_exists('str_putcsv')) {
    /**
     * Convert an array into a CSV line.
     *
     * @param array<mixed> $input     The array to convert to a CSV line
     * @param string       $delimiter Defaults to comma (,)
     * @param string       $enclosure Defaults to double quote (")
     */
    function str_putcsv(array $input, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\'): string
    {
        $fp = fopen('php://temp', 'r+b');
        fputcsv($fp, $input, $delimiter, $enclosure, $escape_char);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), "\n");
        fclose($fp);

        return $data;
    }
}

/**
 * Replaces a property in an object at key with another value.
 *
 * This allows a property in am object to be replaced.  Normally this would not be
 * difficult, unless the target property is an nth level deep object.  This function
 * allows that property to be targeted with a key name in dot-notation.
 *
 * @param stdClass             $target The target in which the property will be replaced
 * @param array<string>|string $key    A key in either an array or dot-notation
 * @param mixed                $value  The value that will be used as the replacement
 *
 * @return bool True if the value was found and replaced.  False otherwise.
 */
function replace_property(stdClass &$target, array|string $key, mixed $value): bool
{
    $cur = &$target;
    $parts = is_array($key) ? $key : explode('.', $key);
    $last = array_pop($parts);
    foreach ($parts as $part) {
        if (!property_exists($cur, $part)) {
            return false;
        }
        $cur = &$cur->{$part};
    }
    $cur->{$last} = $value;

    return true;
}

/**
 * Recrusively convert a traversable object into a normal array.
 *
 * This is the same as the built-in PHP iterator_to_array() function except it will recurse
 * into any \Traversable objects it contains.
 *
 * @param Traversable<mixed> $it the object to convert to an array
 *
 * @return array<mixed>
 */
function recursive_iterator_to_array(Traversable $it): array
{
    $result = [];
    foreach ($it as $key => $value) {
        if ($value instanceof Traversable) {
            $result[$key] = recursive_iterator_to_array($value);
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}

/**
 * Generate a truly random string of characters.
 *
 * @param mixed $length          the length of the random string being created
 * @param mixed $include_special Whether or not special characters.  Normally only Aa-Zz, 0-9 are used.  If TRUE will include
 *                               characters such as #, $, etc.  This can also be a string of characters to use.
 *
 * @return string a totally random string of characters
 */
function str_random($length, $include_special = false)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (true === $include_special) {
        $characters .= ' ~!@#$%^&*()-_=+[{]}\|;:\'",<.>/?';
    } elseif (is_string($include_special)) {
        $characters .= $include_special;
    }
    $count = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; ++$i) {
        $randomString .= $characters[rand(0, $count - 1)];
    }

    return $randomString;
}

class MatchReplaceException extends Exception {}

/**
 * Use the match/replace algorithm on a string to replace mustache tags with view data.
 *
 * This is similar code used in the Smarty view template renderer.
 *
 * So strings such as:
 *
 * * ```Hello, {{entity}}``` will replace {{entity}} with the value of ```$data->entity```.
 * * ```The quick brown {{animal.one}}, jumped over the lazy {{animal.two}}``` will replace the tags with values in a multi-dimensional array.
 *
 * @param string $string the string to perform the match/replace on
 * @param mixed  $data   the data to use for matching
 * @param bool   $strict in strict mode, the function will return NULL if any of the matches are do not exist in data or are NULL
 *
 * @return mixed the modified string with mustache tags replaced with view data, or removed if the view data does not exist
 */
function match_replace(string $string, $data, $strict = false)
{
    try {
        $string = preg_replace_callback('/\{\{([^\}{2}]*)\}\}/', function ($match) use ($data, $strict) {
            $value = '';
            $key = null;
            if ('?' === substr($match[1], 0, 1)) {
                [$test, $output] = explode(':', substr($match[1], 1), 2);
                if ('!' === substr($test, 0, 1)) {
                    $eval = false;
                    $test = substr($test, 1);
                } else {
                    $eval = true;
                }
                if (empty($data[$test] ?? null) !== $eval) {
                    $key = $output;
                }
            } else {
                $key = $match[1];
            }
            if ($key) {
                if ('>' === substr($key, 0, 1)) {
                    $value = substr($key, 1);
                } else {
                    $value = $data[$key] ?? null;
                }
                if (true === $strict && null === $value) {
                    throw new MatchReplaceException();
                }
            }

            return $value;
        }, $string);
    } catch (MatchReplaceException $e) {
        return null;
    }

    return $string;
}

function str_ftime(string $format, ?int $timestamp = null): string
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

/**
 * Object Merge.
 *
 * Performs a similar operation to array_merge() except works with objects.  ANY objects. ;)
 *
 * @param mixed ...$objects Takes 2 or more objects to merge together
 *
 * @return object the merged object with the class of the first object argument
 */
function object_merge(mixed ...$objects): ?object
{
    if (count($objects) < 2 || !is_object($objects[0])) {
        return null;
    }
    $target_reflection = new ReflectionObject($objects[0]);
    $target_object = $target_reflection->newInstance();
    foreach ($objects as $object) {
        if (is_object($object)) {
            $reflection = new ReflectionObject($object);
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                $property->setValue($target_object, $property->getValue($object));
            }
        } elseif (is_array($object)) {
            foreach ($object as $key => $value) {
                $target_object->{$key} = $value;
            }
        }
    }

    return $target_object;
}

/**
 * Returns the path to the PHP binary.
 *
 * This function tries to determine the path to the PHP binary by checking the currently running PHP binary
 * and the system's PATH environment variable. It first checks if the PHP_BINARY constant is defined and
 * uses its directory path. If not, it uses the 'which' command to find the PHP binary in the system's PATH.
 *
 * @return null|string the path to the PHP binary, or null if it cannot be determined
 */
function php_binary(): ?string
{
    // Try and use the currently running PHP binary (this filters out the possibility of FPM)
    $php_binary = (defined('PHP_BINARY') ? dirname(PHP_BINARY).DIRECTORY_SEPARATOR : '').'php';
    if (file_exists($php_binary)) {
        return $php_binary;
    }
    // Look in the path
    $php_binary = exec('which php');
    if (\file_exists($php_binary)) {
        return $php_binary;
    }

    return null;
}

function is_reserved(string $word): bool
{
    $reservedKeywords = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable',
        'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default',
        'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends',
        'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface',
        'isset', 'list', 'match', 'namespace', 'new', 'or', 'print', 'private',
        'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch',
        'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield',
    ];

    return in_array(strtolower($word), $reservedKeywords);
}
