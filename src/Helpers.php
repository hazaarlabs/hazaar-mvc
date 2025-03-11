<?php

declare(strict_types=1);

use Hazaar\Application;
use Hazaar\Controller\Dump;

$dumpLog = [];

/**
 * Logs the provided data with a timestamp.
 *
 * This function logs the provided data with a timestamp to the global log array.  The dump log
 * will be displayed when the dump function is called.  This allows for dumping data without
 * immediately halting the script.
 *
 * @param mixed $data the data to be logged
 *
 * @global array $dumpLog The global log array where the data will be stored.
 */
function dumpLog(mixed $data): void
{
    global $dumpLog;
    $dumpLog[] = ['time' => microtime(true), 'data' => $data];
}

/**
 * Dumps information about one or more variables and halts the execution of the script.
 *
 * This function provides detailed information about the variables passed to it, including
 * their values, types, and a backtrace of the call stack. It can also log the information
 * and output it in different formats depending on the application state.
 *
 * @param mixed ...$data One or more variables to dump.
 *
 * Global Variables:
 *
 * @global array $dumpLog An array to store log entries.
 *
 * The function performs the following actions:
 * - Retrieves the call stack to determine the caller's file, line, function, and class.
 * - If the HAZAAR_VERSION constant is defined and the application instance is available:
 *   - If the router is set, it initializes a Dump controller, adds log entries, and runs the controller.
 *   - Otherwise, it uses var_dump to output the variables.
 * - If the HAZAAR_VERSION constant is not defined:
 *   - It constructs a detailed output string including execution time, end time, variable values, log entries, and backtrace.
 *   - Outputs the constructed string.
 *
 * The function terminates the script execution using exit.
 */
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
        $controller = new Dump($data);
        if (is_array($dumpLog)) {
            $controller->addLogEntries($dumpLog);
        }
        $controller->setCaller($caller);
        $app->run($controller);
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
