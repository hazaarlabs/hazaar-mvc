<?php

declare(strict_types=1);

use Hazaar\Application;
use Hazaar\Application\Request;
use Hazaar\Controller\Dump;

$dumpLog = [];

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
