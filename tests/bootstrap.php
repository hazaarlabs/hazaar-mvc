<?php
define('APPLICATION_PATH', getcwd() . '/tests/application');
require_once ('src/HelperFunctions.php');
//Set up the $_SERVER variable with some stuff that we need and can then predict.
$_SERVER['SERVER_NAME'] = 'www.example.com';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['REQUEST_URI'] = '/test';
