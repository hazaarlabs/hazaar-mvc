<?php

declare(strict_types=1);

namespace Hazaar\Warlock;

include __DIR__.'/../../../../autoload.php';

require_once __DIR__.'/../Constants.php';
require_once 'Server/Functions.php';
if (!extension_loaded('sockets')) {
    exit("The sockets extension is not loaded.\n");
}
if (!extension_loaded('pcntl')) {
    exit("The pcntl extension is not loaded.\n");
}
if (!defined('APPLICATION_PATH')) {
    exit("Warlock can not start without an application path.  Make sure APPLICATION_PATH environment variable is set.\n");
}
if (!(is_dir(APPLICATION_PATH)
    && file_exists(APPLICATION_PATH.DIRECTORY_SEPARATOR.'configs')
    && file_exists(APPLICATION_PATH.DIRECTORY_SEPARATOR.'controllers'))) {
    exit("Application path '".APPLICATION_PATH."' is not a valid application directory!\n");
}
chdir(APPLICATION_PATH);
$ops = getopt('s', ['env:'], $opts);
$env = array_key_exists('env', $ops) ? $ops['env'] : (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development');
define('APPLICATION_ENV', $env);
define('SERVER_PATH', realpath(dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'Warlock'));

include APPLICATION_PATH.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
if (!class_exists('Hazaar\Loader')) {
    throw new \Exception('A Hazaar loader could not be loaded!');
}
$reflector = new \ReflectionClass('Hazaar\Loader');
set_include_path(implode(PATH_SEPARATOR, [
    realpath(dirname($reflector->getFileName())),
    realpath(SERVER_PATH),
    get_include_path(),
]));
$reflector = null;
$log_level = W_INFO;
$warlock = new Server\Master($env, in_array('-s', $argv) ? true : boolify('file' === getenv('WARLOCK_OUTPUT')));

exit($warlock->bootstrap()->run());
