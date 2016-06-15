<?php
/**
 * @file        Hazaar/ErrorControl.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

set_error_handler('error_handler', E_ALL ^ E_NOTICE);

set_exception_handler('exception_handler');

register_shutdown_function('shutdown_handler');

/**
 * @brief       Error and die
 *
 * @since       1.0.0
 */
function errorAndDie() {

    if($app = \Hazaar\Application::getInstance()) {

        if($app->request)
            $app->request->resetAction();

        if($app->config->app->has('errorController')) {

            $loader = \Hazaar\Loader::getInstance();

            try {

                $controller = $loader->loadController($app->config->app['errorController']);

                if(! $controller instanceof \Hazaar\Controller\Error)
                    throw new Exception('Error controller does not extent Hazaar\Controller\Error');

            } catch(Exception $e) {

                $controller = new \Hazaar\Controller\Error('Error', $app);

            }

        } else {

            require_once('Hazaar/Core/Controller/Error.php');

            $controller = new \Hazaar\Controller\Error('Error', $app);

        }

        $controller->__initialize($app->request);

        call_user_func_array(array(
                                 $controller,
                                 'setError'
                             ), func_get_args());

        $controller->clean_output_buffer();
        
        $app->run($controller);

    } else {

        $arg = func_get_args();

        ?>
        <html>
        <head>

            <title>Hazaar MVC - Fatal Error</title>

            <style>
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    padding: 0;
                    margin: 0;
                    color: #333;
                }

                .container {
                    max-width: 1170px;
                    margin: auto;
                }

                .topbar {
                    background-color: #554D7C;
                    color: #fff;
                    padding: 15px;
                }

                h1 {
                    margin-bottom: 0;
                }

                h3 {
                    margin-top: 0;
                    font-weight: normal;
                    font-style: italic;
                }

                table {
                    font-size: 18px;
                    width: 100%;
                    border-collapse: collapse;
                }

                table th {
                    color: #554D7C;
                }

                table td, table th {
                    padding: 15px 15px 15px 0;
                }

                table.trace {
                    background: #eee;
                }

                table.trace th {
                    border-bottom: 1px solid #554D7C;
                }

                table.trace th, table.trace td {
                    padding: 15px;
                }

                table.trace tbody tr:nth-child(2) {
                }

                th {
                    text-align: left;
                    vertical-align: top;
                    width: 50px;
                }
            </style>
        </head>
        <body>

        <div class="topbar">

            <div class="container">

                <h1>FATAL ERROR</h1>

                <h3>An error occurred without an application context...</h3>

            </div>

        </div>


        <div class="container">

            <h2><?=$arg[0]->getMessage();?></h2>

            <table>
                <tr>
                    <th>File:</th>
                    <td><?=$arg[0]->getFile();?></td>
                </tr>
                <tr>
                    <th>Line:</th>
                    <td><?=$arg[0]->getLine();?></td>
                </tr>
                <tr>
                    <th>Trace:</th>
                    <td>
                        <table class="trace">

                            <thead>

                            <tr>
                                <th>Step</th>
                                <th>File</th>
                                <th>Line</th>
                                <th>Function</th>
                            </tr>

                            </thead>

                            <tbody>

                            <?php foreach($arg[0]->getTrace() as $id => $trace) { ?>

                                <tr>
                                    <td><?=$id;?></td>
                                    <td><?=$trace['file'];?></td>
                                    <td><?=$trace['line'];?></td>
                                    <td><?=($trace['class'] ? $trace['class'] . '::' : NULL) . $trace['function'] . '(' . ($trace['args'] ? implode(', ', $trace['args']) : NULL) . ')';?></td>
                                </tr>

                            <?php } ?>

                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        </body>
        </html>
        <?php

    }

    exit;

}

function error_handler($errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL) {

    errorAndDie($errno, $errstr, $errfile, $errline, $errcontext, debug_backtrace());

}

function exception_handler($e) {

    errorAndDie($e);

}

function shutdown_handler() {

    $error_notices = array(
        1,
        4,
        16,
        64,
        256
    );

    $error = error_get_last();

    if(in_array($error['type'], $error_notices)) {

        errorAndDie($error, debug_backtrace());

    }

}

function basic_handler($errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL) {

    echo "PHP Error #$errno: $errstr in file $errfile on line $errline";

    debug_print_backtrace();

    die();

}

if(function_exists('apache_get_modules')) {

    if(! in_array('mod_rewrite', apache_get_modules())) {

        throw new \Exception('mod_rewrite MUST be enabled to use Hazaar!');

    }

}

function traceAndDie(){
	
	$trace = debug_backtrace();
	 
	?>
	<html>
	<head>
	<style>
	table {
		border-collapse: collapse;
		margin: auto;
	}
	table th {
		text-align: left;
		border-bottom: 1px solid #ddd;
		background: #eee;
	}
	table th, table td {
		padding: 5px;
	}
	</style>
	</head>
	<table><tr><th>File</th><th>Line</th><th>Function</th><th>Args</th></tr>
	
	<?php
	 
	foreach($trace as $t){
		
		echo "<tr><td>" . ake($t, 'file', 'unknown') . "</td><td>" . ake($t, 'line') . "</td><td>" . $t['function'] . "</td>";
		
		if($args = ake($t, 'args')){
			
			$arglist = array();
			
			foreach($args as $a){

				if(is_object($a)){
					
					$arglist[] = "Object(" . get_class($a) . ")";
					
				}else{
					
					$arglist[] = gettype($a) . "($a)";
					
				}
				
			}
			
			echo "<td>" . implode(", ", $arglist) . "</td>";
			
		}
		
		echo "</tr>";
	
	}
	 
	?>
	</table>
	
	</html>
	
	<?php
	 
	exit;
	
}
