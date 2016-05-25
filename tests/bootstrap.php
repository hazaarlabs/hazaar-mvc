<?php
define('APPLICATION_PATH', getcwd() . '/tests/application');
set_include_path(implode(PATH_SEPARATOR, array(
    getcwd(),
    get_include_path(),
)));
require_once ('Hazaar/HelperFunctions.php');
require_once ('Hazaar/Loader.php');
$loader = Hazaar\Loader::getInstance();
$loader->register();
//Set up the $_SERVER variable with some stuff that we need and can then predict.
$_SERVER['SERVER_NAME'] = 'www.example.com';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['REQUEST_URI'] = '/test';
