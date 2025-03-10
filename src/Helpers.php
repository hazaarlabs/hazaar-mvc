<?php

declare(strict_types=1);

use Hazaar\Application;
use Hazaar\Application\Request;
use Hazaar\Controller\Dump;

$dumpLog = [];



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
