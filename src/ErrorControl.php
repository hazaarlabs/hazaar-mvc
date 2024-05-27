<?php

declare(strict_types=1);

use Hazaar\Application;
use Hazaar\Controller\Response\Stream;
use Hazaar\Logger\Frontend;

/*
 * @file        Hazaar/ErrorControl.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
set_error_handler('error_handler', E_ERROR);
set_exception_handler('exception_handler');
register_shutdown_function('shutdown_handler');

/**
 * This function handles errors and terminates the script execution.
 */
function errorAndDie(): void
{
    $code = 0;
    $app = Application::getInstance();
    $headers = array_unflatten(headers_list(), ':', "\n");
    $args = func_get_args();
    if ('stream' == ake($headers, 'X-Response-Type')) {
        $stream = new Stream($args[0]);
        $stream->__writeOutput();
    } elseif ($app instanceof Application) {
        $controller = $app->router->getErrorController();
        call_user_func_array([$controller, 'setError'], $args);
        $controller->cleanOutputBuffer();
        $code = $app->run($controller);
    } else {
        $error = [10500, 'An unknown error occurred!', __FILE__, __LINE__, null, []];
        if (count($args) > 0) {
            if ($args[0] instanceof Exception
                || $args[0] instanceof Error) {
                $error = [
                    $args[0]->getCode(),
                    $args[0]->getMessage(),
                    $args[0]->getFile(),
                    $args[0]->getLine(),
                    null,
                    $args[0]->getTrace(),
                ];
            } elseif (isset($args[0]) && is_array($args[0]) && array_key_exists('type', $args[0])) {
                $error = [
                    $args[0]['type'],
                    $args[0]['message'],
                    $args[0]['file'],
                    $args[0]['line'],
                    null,
                    isset($args[1]) ? $args[1] : null,
                ];
            } else {
                $error = $args;
            }
            if ('cli' === php_sapi_name()) {
                $die = "##############################\n# Hazaar MVC - Console Error #\n##############################\n\n";

                $die .= "{$error[1]}\n\n";
                if (!is_array($error[5])) {
                    $error[5] = [];
                }
                $error[5][] = ['file' => $error[2], 'line' => $error[3], 'class' => '', 'function' => ''];
                $die .= "Call stack:\n\n";
                for ($x = count($error[5]) - 1; $x >= 0; --$x) {
                    $die .= count($error[5]) - $x.". {$error[5][$x]['class']}->{$error[5][$x]['function']} {$error[5][$x]['file']}:{$error[5][$x]['line']}\n";
                }

                exit($die);
            }
        }

        include realpath(__DIR__
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'libs'
            .DIRECTORY_SEPARATOR.'error'
            .DIRECTORY_SEPARATOR.'fatal.php');
        $code = $error[0];
    }

    exit($code);
}

/**
 * Terminates the script execution and displays an error message.
 *
 * This function is used to handle errors and terminate the script execution. It clears the output buffer, sets the HTTP response code to 500, and displays an error message with the provided error information.
 *
 * @param string|Throwable $err the error message or Throwable object
 */
function dieDieDie(string|Throwable $err): void
{
    while (count(ob_get_status()) > 0) {
        ob_end_clean();
    }
    $code = 500;
    $err_string = 'An unknown error has occurred';
    if ($err instanceof Throwable) {
        $err_string = $err->getMessage();
        if (boolify(ini_get('display_errors'))) {
            $err_string .= "\n\non line ".$err->getLine().' of file '.$err->getFile()."\n\n".$err->getTraceAsString();
        }
    } elseif (is_string($err)) {
        $err_string = $err;
    }
    if ('cli' === php_sapi_name()) {
        $msg = "HazaarMVC ERROR: {$err_string}\n";
    } else {
        http_response_code($code);
        $msg = '<h1>'.http_response_text(http_response_code())."</h1><pre>{$err_string}</pre>"
            .'<hr/><i>Hazaar MVC/'.HAZAAR_VERSION
            .' ('.php_uname('s').')';
        if (array_key_exists('SERVER_NAME', $_SERVER)) {
            $msg .= ' Server at '.$_SERVER['SERVER_NAME'].' Port '.$_SERVER['SERVER_PORT'].'</i>';
        }
    }

    exit($msg);
}

/**
 * Custom error handler function.
 *
 * This function is responsible for handling PHP errors and displaying appropriate error messages.
 *
 * @param int         $errno   the error number
 * @param string      $errstr  the error message
 * @param null|string $errfile the file where the error occurred
 * @param null|int    $errline the line number where the error occurred
 *
 * @return bool returns true to prevent the default PHP error handler from being called
 */
function error_handler(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
{
    if ($errno >= 500) {
        Frontend::e('CORE', "Error #{$errno} on line {$errline} of file {$errfile}: {$errstr}");
    }

    errorAndDie($errno, $errstr, $errfile, $errline, debug_backtrace());

    return true;
}

/**
 * Exception handler function.
 *
 * This function is responsible for handling exceptions thrown in the application.
 * If the exception code is greater than or equal to 500, it logs the error message
 * along with the code, line number, and file name. Then it calls the `errorAndDie()`
 * function to handle the error further.
 *
 * @param Throwable $e the exception object
 */
function exception_handler(Throwable $e): void
{
    if ($e->getCode() >= 500) {
        Frontend::e('CORE', 'Error #'.$e->getCode().' on line '.$e->getLine().' of file '.$e->getFile().': '.$e->getMessage());
    }
    errorAndDie($e);
}

/**
 * Shutdown handler function.
 *
 * This function is responsible for executing the shutdown tasks registered in the global variable $__shutdown_tasks.
 * It checks if the script is running in CLI mode or if headers have already been sent before executing the tasks.
 */
function shutdown_handler(): void
{
    if ('cli' !== php_sapi_name() && headers_sent()) {
        return;
    }
    global $__shutdown_tasks;
    if (is_array($__shutdown_tasks) && count($__shutdown_tasks) > 0) {
        foreach ($__shutdown_tasks as $task) {
            $task();
        }
    }
}

/**
 * Handles basic PHP errors.
 *
 * @param int         $errno   the error number
 * @param string      $errstr  the error message
 * @param null|string $errfile the file where the error occurred
 * @param null|int    $errline the line number where the error occurred
 */
function basic_handler(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): void
{
    dieDieDie("PHP Error #{$errno}: {$errstr} in file {$errfile} on line {$errline}");
}

/**
 * Traces the current execution and terminates the script.
 *
 * This function generates a backtrace of the current execution and includes a trace file for debugging purposes.
 * After generating the trace, it terminates the script using the `exit` function.
 */
function traceAndDie(): void
{
    $trace = debug_backtrace();

    include realpath(__DIR__
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'libs'
            .DIRECTORY_SEPARATOR.'error'
            .DIRECTORY_SEPARATOR.'trace.php');

    exit;
}

/**
 * Registers a shutdown task to be executed when the script ends.
 *
 * @param callable    $callback the callback function to be executed
 * @param null|string $uniq_id  (optional) A unique identifier for the task. If not provided, a unique ID will be generated.
 */
function register_shutdown_task(callable $callback, ?string $uniq_id = null): void
{
    // Function implementation...
}
