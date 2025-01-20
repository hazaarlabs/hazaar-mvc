<?php
//Set up the $_SERVER variable with some stuff that we need and can then predict.
$_SERVER['SERVER_NAME'] = 'www.example.com';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['REQUEST_URI'] = '/test';
$GLOBALS['app'] = $app = new \Hazaar\Application(APPLICATION_ENV, realpath(__DIR__ . '/application'));
$GLOBALS['cache']['hazaar-auth'] = hash('ripemd128', '12345');
$app->bootstrap();
