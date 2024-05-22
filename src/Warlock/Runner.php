<?php

declare(strict_types=1);
use Hazaar\Application;
use Hazaar\Warlock\Process;

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', ($path = getenv('APPLICATION_PATH'))
    ? $path : realpath(dirname(__FILE__).'/../../../../application'));
// Define application environment
defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development');
$path = dirname(__FILE__).DIRECTORY_SEPARATOR.'..';
define('SERVER_PATH', realpath($path));

// Composer autoloading
include APPLICATION_PATH.'/../vendor/autoload.php';
$reflector = new ReflectionClass('Hazaar\Loader');
set_include_path(implode(PATH_SEPARATOR, [
    realpath(dirname($reflector->getFileName())),
    realpath(SERVER_PATH),
    get_include_path(),
]));
$reflector = null;
// Create application, bootstrap, and run
$application = new Application(APPLICATION_ENV);

exit(Process::runner($application->bootstrap(), $argv));
