<?php
/**
 * @file        Hazaar/ErrorControl.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

set_error_handler('error_handler', E_ERROR);

set_exception_handler('exception_handler');

register_shutdown_function('shutdown_handler');

/**
 * @brief       Error and die
 *
 * @since       1.0.0
 */
function errorAndDie() {

    $app = \Hazaar\Application::getInstance();

    $headers = array_unflatten(headers_list(), ':', "\n");

    $args = func_get_args();

    if(ake($headers, 'X-Response-Type') == 'stream'){

        $stream = new \Hazaar\Controller\Response\Stream($args[0]);

        $stream->__writeOutput();

    }elseif($app instanceof Hazaar\Application && $app->config) {

        $controller = null;

        if($error_controller = $app->config->app->get('errorController')) {

            $loader = \Hazaar\Loader::getInstance();

            $controller = $loader->loadController($error_controller);

        }

        if(!$controller instanceof \Hazaar\Controller\Error)
            $controller = new \Hazaar\Controller\Error('Error', $app);

        call_user_func_array([$controller, 'setError'], $args);

        $controller->clean_output_buffer();

        $app->run($controller);

    } else {

        $error = [10500, 'An unknown error occurred!', __FILE__, __LINE__, null, []];

        if(count($args) > 0){

            if($args[0] instanceof \Exception
                || $args[0] instanceof \Error){

                $error = [
                    $args[0]->getCode(),
                    $args[0]->getMessage(),
                    $args[0]->getFile(),
                    $args[0]->getLine(),
                    null,
                    $args[0]->getTrace()
                ];

            }elseif(isset($args[0]) && is_array($args[0]) && array_key_exists('type', $args[0])){

                $error = [
                    $args[0]['type'],
                    $args[0]['message'],
                    $args[0]['file'],
                    $args[0]['line'],
                    null,
                    (isset($args[1]) ? $args[1] : null)
                ];

            }else{

                $error = $args;

            }

            if(php_sapi_name() === 'cli'){

                $die = "##############################\n# Hazaar MVC - Console Error #\n##############################\n\n";
                
                $die .= "$error[1]\n\n";

                if(!is_array($error[5]))
                    $error[5] = [];

                $error[5][] = ['file' => $error[2], 'line' => $error[3], 'class' => '', 'function' => ''];

                $die .= "Call stack:\n\n";

                for($x = count($error[5]) - 1; $x >= 0; $x--)
                    $die .= count($error[5]) - $x . ". {$error[5][$x]['class']}->{$error[5][$x]['function']} {$error[5][$x]['file']}:{$error[5][$x]['line']}\n";

                die($die);

            }

        }

        include(realpath(__DIR__
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'libs'
            . DIRECTORY_SEPARATOR . 'error'
            . DIRECTORY_SEPARATOR . 'fatal.php'));

    }

    exit;

}

function dieDieDie($err){

    while(count(ob_get_status()) > 0)
        ob_end_clean();

    $code = 500;

    $err_string = 'An unknown error has occurred';

    if($err instanceof \Exception || $err instanceof \Error){

        $err_string = $err->getMessage();

        if(boolify(ini_get('display_errors')))
            $err_string .= "\n\non line " . $err->getLine() . " of file " . $err->getFile() . "\n\n" . $err->getTraceAsString();

    }elseif(is_string($err)){

        $err_string = $err;

    }

    http_response_code($code);

    die('<h1>' . http_response_text(http_response_code()) . "</h1><pre>$err_string</pre>"
        . "<hr/><i>Hazaar MVC/" . HAZAAR_VERSION 
        . ' (' . php_uname('s') . ')'
        . " Server at " . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] . "</i>");

}

function error_handler($errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL) {

    if($errno >= 500)
        \Hazaar\Logger\Frontend::e('CORE', "Error #$errno on line $errline of file $errfile: $errstr");

    errorAndDie($errno, $errstr, $errfile, $errline, $errcontext, debug_backtrace());

}

function exception_handler($e) {

    if($e->getCode() >= 500)
        \Hazaar\Logger\Frontend::e('CORE', 'Error #' . $e->getCode() . ' on line ' . $e->getLine() . ' of file ' . $e->getFile() . ': ' . $e->getMessage());

    errorAndDie($e);

}

function shutdown_handler() {

    if(headers_sent())
        return;

    if($error = error_get_last()){

        $ignored_errors = [
            E_CORE_WARNING,
            E_COMPILE_WARNING,
            E_USER_WARNING,
            E_RECOVERABLE_ERROR
        ];

        if(is_array($error) && !in_array($error['type'], $ignored_errors))
            errorAndDie($error, debug_backtrace());

    }

    global $__shutdown_tasks;

    if(is_array($__shutdown_tasks) && count($__shutdown_tasks) > 0){

        foreach($__shutdown_tasks as $task)
            $task();

    }
        

}

function basic_handler($errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL) {

    dieDieDie("PHP Error #$errno: $errstr in file $errfile on line $errline");

}

if(function_exists('apache_get_modules')) {

    if(! in_array('mod_rewrite', apache_get_modules()))
        throw new \Hazaar\Exception('mod_rewrite MUST be enabled to use Hazaar!');

}

function traceAndDie(){

	$trace = debug_backtrace();

    include(realpath(__DIR__
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'libs'
            . DIRECTORY_SEPARATOR . 'error'
            . DIRECTORY_SEPARATOR . 'trace.php'));

	exit;

}

function register_shutdown_task($callback, $uniq_id = null){

    if($uniq_id === null)
        $uniq_id = uniqid();

    global $__shutdown_tasks;

    if(!is_array($__shutdown_tasks))
        $__shutdown_tasks = [];

    if(array_key_exists($uniq_id, $__shutdown_tasks))
        return;

    $__shutdown_tasks[$uniq_id] = $callback;

}
