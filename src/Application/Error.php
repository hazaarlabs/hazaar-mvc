<?php

declare(strict_types=1);

namespace Hazaar\Application;

use Hazaar\Application;
use Hazaar\Controller\Response\Stream;
use Hazaar\HTTP\Response;
use Hazaar\Util\Arr;
use Hazaar\Util\Boolean;

class Error
{
    /**
     * This function handles errors and terminates the script execution.
     */
    public static function errorAndDie(): void
    {
        $code = 0;
        $app = Application::getInstance();
        $headers = Arr::unflatten(headers_list(), ':', "\n");
        $args = func_get_args();
        if ('stream' == ($headers['X-Response-Type'] ?? null)) {
            $stream = new Stream($args[0]);
            $stream->writeOutput();
        } elseif (($app instanceof Application && isset($app->router) && $controller = $app->router->getErrorController())
            || ($controller = new \Hazaar\Controller\Error())) {
            call_user_func_array([$controller, 'setError'], $args);
            $controller->cleanOutputBuffer();
            $code = $app->run($controller);
        } else {
            $error = [10500, 'An unknown error occurred!', __FILE__, __LINE__, null, []];
            if (count($args) > 0) {
                if (
                    $args[0] instanceof \Exception
                    || $args[0] instanceof Error
                ) {
                    $error = [
                        'code' => $args[0]->getCode(),
                        'message' => $args[0]->getMessage(),
                        'file' => $args[0]->getFile(),
                        'line' => $args[0]->getLine(),
                        'context' => null,
                        'trace' => $args[0]->getTrace(),
                    ];
                } elseif (isset($args[0]) && is_array($args[0]) && array_key_exists('type', $args[0])) {
                    $error = [
                        'code' => $args[0]['type'],
                        'message' => $args[0]['message'],
                        'file' => $args[0]['file'],
                        'line' => $args[0]['line'],
                        'trace' => isset($args[1]) ? $args[1] : null,
                    ];
                } else {
                    $error = [
                        'code' => $args[0],
                        'message' => $args[1],
                        'file' => $args[2],
                        'line' => $args[3],
                        'trace' => isset($args[4]) ? $args[4] : null,
                    ];
                }
                if ('cli' === php_sapi_name()) {
                    $die = "##############################\n# Hazaar - Console Error #\n##############################\n\n";
                    $die .= "{$error['message']}\n\n";
                    if (!is_array($error['trace'])) {
                        $error['trace'] = debug_backtrace();
                    }
                    $die .= "Call stack:\n\n";
                    foreach ($error['trace'] as $x => $trace) {
                        $die .= count($error['trace']) - $x.'. '
                            .(array_key_exists('class', $trace) ? ". {$trace['class']}->" : '')
                            .(array_key_exists('function', $trace) ? "{$trace['function']}" : '')
                            .(array_key_exists('file', $trace) ? "{$trace['file']}:{$trace['line']}\n" : '');
                    }

                    exit($die);
                }
            }
            echo 'FATAL ERROR';
            var_dump($error);
            $code = $error['code'];
        }

        exit($code);
    }

    /**
     * Terminates the script execution and displays an error message.
     *
     * This function is used to handle errors and terminate the script execution. It clears
     * the output buffer, sets the HTTP response code to 500, and displays an error message
     * with the provided error information.
     *
     * @param string|\Throwable $err The error message or Throwable object
     */
    public static function dieDieDie(string|\Throwable $err): void
    {
        while (count(ob_get_status()) > 0) {
            ob_end_clean();
        }
        $code = 500;
        $errString = 'An unknown error has occurred';
        if ($err instanceof \Throwable) {
            $errString = $err->getMessage();
            if (Boolean::from(ini_get('display_errors'))) {
                $errString .= "\n\non line ".$err->getLine().' of file '.$err->getFile()."\n\n".$err->getTraceAsString();
            }
        } elseif (is_string($err)) {
            $errString = $err;
        }
        if ('cli' === php_sapi_name()) {
            $msg = "Hazaar ERROR: {$errString}\n";
        } else {
            http_response_code($code);
            ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hazaar Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            margin: 0;
            padding: 20px;
        }

        h1 {
            color: #554d7c;
        }

        pre {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }

        hr {
            border: none;
            border-top: 1px solid #dee2e6;
            margin: 20px 0;
        }

        i {
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <h1><?php echo Response::getText(http_response_code()); ?></h1>
    <pre><?php echo htmlspecialchars($errString, ENT_QUOTES, 'UTF-8'); ?></pre>
    <hr />
    <i>Hazaar/<?php echo HAZAAR_VERSION.' ('.php_uname('s').')';
            if (array_key_exists('SERVER_NAME', $_SERVER)) {
                echo ' Server at '.htmlspecialchars($_SERVER['SERVER_NAME'], ENT_QUOTES, 'UTF-8').' Port '.htmlspecialchars($_SERVER['SERVER_PORT'], ENT_QUOTES, 'UTF-8').'</i>';
            } ?>
</body>

</html> <?php
        }

        exit($code);
    }

    /**
     * Traces the current execution and terminates the script.
     *
     * This function generates a backtrace of the current execution and includes a trace file for debugging purposes.
     * After generating the trace, it terminates the script using the `exit` function.
     */
    public static function traceAndDie(): void
    {
        $trace = debug_backtrace();

        include realpath(__DIR__
            .DIRECTORY_SEPARATOR.'..'
            .DIRECTORY_SEPARATOR.'libs'
            .DIRECTORY_SEPARATOR.'error'
            .DIRECTORY_SEPARATOR.'trace.php');

        exit;
    }
}
?>