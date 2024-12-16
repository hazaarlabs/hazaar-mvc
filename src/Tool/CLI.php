<?php

use Hazaar\Application;
use Hazaar\Application\Request\CLI;
use Hazaar\Tool\Main;

// Define path to application directory
defined('APPLICATION_PATH') || define(
    'APPLICATION_PATH',
    ($path = getenv('APPLICATION_PATH'))
    ? $path
    : realpath(__DIR__.'/../../../../../application')
);

// Define application environment
defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development');

define('SERVER_PATH', realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'src'));

// Composer autoloading
if (file_exists($autoload = __DIR__.'/../../vendor/autoload.php')) {
    include $autoload;
} else {
    include APPLICATION_PATH.'/../vendor/autoload.php';
}

$reflector = new ReflectionClass('Hazaar\Loader');

set_include_path(implode(PATH_SEPARATOR, [
    realpath(dirname($reflector->getFileName())),
    realpath(SERVER_PATH),
    get_include_path(),
]));

$reflector = null;

// Create application, bootstrap, and run
$application = new Application('tool');
$request = new CLI($argv);

exit(Main::run($application, $request));
