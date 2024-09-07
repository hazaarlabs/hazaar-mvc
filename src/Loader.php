<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Loader.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright(c)2012 Jamie Carl(http://www.hazaar.io)
 */

namespace Hazaar;

// Constant to indicate a path contains config files
define('FILE_PATH_ROOT', 'root');
// Constant to indicate a path contains config files
define('FILE_PATH_CONFIG', 'config');
// Constant to indicate a path contains model classes
define('FILE_PATH_MODEL', 'model');
// Constant to indicate a path contains view files
define('FILE_PATH_VIEW', 'view');
// Constant to indicate a path contains controller classes
define('FILE_PATH_CONTROLLER', 'controller');
// Constant to indicate a path contains service classes
define('FILE_PATH_SERVICE', 'service');
// Constant to indicate a path contains Support files
define('FILE_PATH_SUPPORT', 'support');
// Constant to indicate a path contains Helper files
define('FILE_PATH_HELPER', 'helper');
// Constant to indicate a path in the library path
define('FILE_PATH_LIB', 'library');
// Constant to indicate a path in the public path
define('FILE_PATH_PUBLIC', 'public');
define('LINE_BREAK', "\n");
// Constant containing the path in which the current application resides.
defined('APPLICATION_PATH') || define('APPLICATION_PATH', getApplicationPath($_SERVER['SCRIPT_FILENAME']));
// Constant containing the application base path relative to the document root.
define('APPLICATION_BASE', dirname($_SERVER['SCRIPT_NAME']));
// Constant containing the name of the user running the script
define('APPLICATION_USER', \posix_getpwuid(\posix_geteuid())['name']);
// Constant containing the absolute filesystem path that contains the whole project.
define('ROOT_PATH', realpath(APPLICATION_PATH.DIRECTORY_SEPARATOR.'..'));
// Constant containing the absolute filesystem path to the default configuration directory.
define('CONFIG_PATH', realpath(APPLICATION_PATH.DIRECTORY_SEPARATOR.'configs'));
// Constant containing the absolute filesystem path to the application public directory.
define('PUBLIC_PATH', realpath(APPLICATION_PATH.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'public'));
// Constant containing the absolute filesystem path to the HazaarMVC library
define('LIBRARY_PATH', realpath(dirname(__FILE__)));
// Constant containing the absolute filesystem path to the HazaarMVC support library
define('SUPPORT_PATH', realpath(LIBRARY_PATH.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'libs'));

/**
 * Global class file loader.
 *
 * This class contains methods for auto-loading classes from files in the Hazaar library path. Ordinarily
 * there will be no need for developers to use this class directly but it does contain a few methods for
 * working with paths and library files.
 *
 * This class is not meant to be instantiated directly and instances should be retrieved using the
 * Loader::getInstance()method.
 *
 * ### Example
 *
 * ```php
 * $loader = Hazaar\Loader::getInstance();
 * ```
 *
 * !!! notice
 * The loader class is loaded automatically when starting the application.  There should be no need to use the
 * Loader instance directly and static methods have been provided for some extra functionality.
 *
 * !!! warning
 * Instantiating this class directly can have undefined results.
 */
class Loader
{
    /**
     * @var array<string, array<string>> an array of search paths for this loader instance
     */
    public array $paths = [];
    private static ?Loader $instance = null;

    /**
     * Initialise a new loader.
     *
     * !!! warning
     * Do NOT instantiate this class directly. See Loader::getInstance() on how to get a new Loader instance.
     */
    public function __construct()
    {
        if (!Loader::$instance instanceof Loader) {
            Loader::$instance = $this;
        }
        // Add some default search paths
        $this->addSearchPath(FILE_PATH_ROOT, ROOT_PATH);
        if (defined('CONFIG_PATH') && CONFIG_PATH) {
            $this->addSearchPath(FILE_PATH_CONFIG, CONFIG_PATH);
        }
        if (defined('LIBRARY_PATH') && LIBRARY_PATH) {
            $this->addSearchPath(FILE_PATH_LIB, LIBRARY_PATH);
        }
        if (defined('PUBLIC_PATH') && PUBLIC_PATH) {
            $this->addSearchPath(FILE_PATH_PUBLIC, PUBLIC_PATH);
        }
        if (defined('SUPPORT_PATH') && SUPPORT_PATH) {
            $this->addSearchPath(FILE_PATH_SUPPORT, SUPPORT_PATH);
        }
    }

    public static function fixDirectorySeparator(string $path): string
    {
        return str_replace((DIRECTORY_SEPARATOR == '/') ? '\\' : '/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Return the current instance of the Loader object.
     */
    public static function getInstance(): Loader
    {
        if (null === Loader::$instance) {
            Loader::$instance = new Loader();
        }

        return Loader::$instance;
    }

    /**
     * Register this loader instance as a class autoloader.
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClassFromFile']);
    }

    /**
     * Unregister this loader instance as a class autoloader.
     */
    public function unregister(): void
    {
        spl_autoload_unregister([$this, 'loadClassFromFile']);
    }

    public function addIncludePath(string $path): void
    {
        set_include_path(get_include_path().PATH_SEPARATOR.$path);
    }

    /**
     * Add a new search path for loading classes from library files.
     *
     * The path type can be anything if you are using the loader to load your own library files. There are
     * built in path types for loading Hazaar library files.
     *
     * * FILE_PATH_ROOT - Path that contains the whole project
     * * FILE_PATH_MODEL - Path contains model classes
     * * FILE_PATH_VIEW - Path contains view files.
     * * FILE_PATH_CONTROLLER - Path contains controller classes.
     * * FILE_PATH_SUPPORT - Path contains support files. Used by the Application::runDirect()method.
     * * FILE_PATH_CONFIG - Configuration files
     *
     * @param string $type the path type to add
     * @param string $path the path to add
     */
    public function addSearchPath(string $type, string $path): bool
    {
        if (!is_string($path)) {
            return false;
        }
        if ($path = realpath($path)) {
            if (!array_key_exists($type, $this->paths) || !in_array($path, $this->paths[$type])) {
                $this->paths[$type][] = $path;
            }

            return true;
        }

        return false;
    }

    /**
     * Sets the search path for a file type.
     *
     * This is the same as addSearchPath except that it overwrites any existing paths.
     *
     * @param mixed $type the path type to add
     * @param mixed $path the path to add
     *
     * @return bool
     */
    public function setSearchPath($type, $path)
    {
        $this->paths[$type] = [];

        return $this->addSearchPath($type, $path);
    }

    /**
     * Add multiple search paths from an array.
     *
     * @param array<mixed> $array Array containing type/path pairs
     */
    public function addSearchPaths(array $array): void
    {
        foreach ($array as $type => $path) {
            $this->addSearchPath($type, APPLICATION_PATH.DIRECTORY_SEPARATOR.$path);
        }
    }

    /**
     * Return an array of search paths for this loader instance.
     *
     * @return array<mixed> Array of search paths
     */
    public function getSearchPaths(?string $type = null): ?array
    {
        if ($type) {
            if (array_key_exists($type, $this->paths)) {
                return $this->paths[$type];
            }
        } else {
            return $this->paths;
        }

        return null;
    }

    /**
     * Checks if a given path is an absolute path.
     *
     * @param string $path the path to check
     *
     * @return bool returns true if the path is an absolute path, false otherwise
     */
    public static function isAbsolutePath(string $path): bool
    {
        return ':' == substr($path, 1, 1) || DIRECTORY_SEPARATOR == substr($path, 0, 1);
    }

    /**
     * Return the absolute filesystem path to a file.
     * By default this method uses the application
     * path as the base path.
     *
     * This method also checks that the file exists. If the file does not exist then null will be
     * returned.
     *
     * @param string $type             The path type to search. See Loader::addSearchPath()
     * @param string $base_path        The path to use as a search base if there are no paths of the requested type
     * @param bool   $case_insensitive By default paths are case sensitive. In some circumstances this might not be
     *                                 desirable so set this TRUE to perform a(slower)case insensitive search.
     *
     * @return string The absolute path to the file if it exists. NULL otherwise.
     */
    public static function getFilePath(string $type, ?string $search_file = null, ?string $base_path = null, bool $case_insensitive = false): ?string
    {
        if (!$base_path) {
            $base_path = APPLICATION_PATH;
        }
        $loader = Loader::getInstance();
        if ($search_file) {
            $search_file = Loader::fixDirectorySeparator($search_file);
            // If the search file is an absolute path just return it if it exists.
            if (Loader::isAbsolutePath($search_file)) {
                return Loader::resolveRealPath($search_file);
            }
        }
        if ($paths = $loader->getSearchPaths($type)) {
            foreach ($paths as $path) {
                $filename = $path.DIRECTORY_SEPARATOR.$search_file;
                if ($realpath = Loader::resolveRealPath($filename, $case_insensitive)) {
                    return $realpath;
                }
            }
        } else {
            $absolute_path = $base_path.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$search_file;
            if (file_exists($absolute_path)) {
                return realpath($absolute_path);
            }
        }

        return null;
    }

    /**
     * Resolve a filename within any of the search paths.
     *
     * @return string Absolute path to the file
     */
    public static function resolve(string $filename): ?string
    {
        $paths = explode(PATH_SEPARATOR, get_include_path());
        foreach ($paths as $path) {
            $target = $path.DIRECTORY_SEPARATOR.'Hazaar'.DIRECTORY_SEPARATOR.$filename;
            if (file_exists($target)) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Loads a class from a source file.
     * This is the main class loader used by the __autoload()PHP
     * trigger. It is responsible for loading the files that hold class source definitions by determining
     * the correct file to load based on the class name.
     *
     * First check if the class name is a single word that ends with 'Controller', designating it as a
     * controller class. If that matches then the class is loaded from the controller path.
     *
     * Otherwise we check if the class starts with Application and load from the application path.
     *
     * Lastly we do a 2 stage search of the library paths. Stage 1 looks for a correlating path while
     * stage
     * 2 looks for the class in a sub-directory of the module name.
     *
     * We do 2 stage class path checking.
     *
     * * _Stage 1:_ Look for the class in a correlating path. eg:[[Hazaar\Application]] in path
     * Hazaar/Application.php
     * * _Stage 2:_ If stage 1 fails, look in a module sub-directory. eg:[[Hazaar\Application]] in path
     * Hazaar/Application/Application.php
     *
     * If they both fail, the class is not found and we throw a pretty exception.
     *
     * @param string $className
     *                          The name of the class to load
     */
    public static function loadClassFromFile($className): void
    {
        $namepath = preg_split('/(\W|_)/', $className, -1, PREG_SPLIT_NO_EMPTY);
        /*
         * Check that the prefix is 'Application'. This is sort of a namespace 'key' if you will
         * to restrict the loadable path to that of the application itself.
         */
        if ('Application' === $namepath[0]) {
            $filename = implode(DIRECTORY_SEPARATOR, array_slice($namepath, 2)).'.php';
            if ($full_path = Loader::getFilePath(strtolower($namepath[1]), $filename, null, true)) {
                require_once $full_path;
            }
        }
    }

    private static function resolveRealPath(string $filename, bool $case_insensitive = false): ?string
    {
        if (file_exists($filename)) {
            return realpath($filename);
        }
        if ($case_insensitive) {
            $dirname = dirname($filename);
            $filename = strtolower(basename($filename));
            if (!file_exists($dirname)) {
                return null;
            }
            $dir = dir($dirname);
            while (($file = $dir->read()) !== false) {
                if ('.' == substr($file, 0, 1)) {
                    continue;
                }
                if (strtolower($file) == $filename) {
                    return realpath($dirname.DIRECTORY_SEPARATOR.$file);
                }
            }
        }

        return null;
    }
}

function getApplicationPath(?string $search_path = null): false|string
{
    if ($path = getenv('APPLICATION_PATH')) {
        return realpath($path);
    }
    $search_path = (null === $search_path) ? getcwd() : realpath($search_path);
    $count = 0;
    do {
        if (':' === substr($search_path, 1, 1)) {
            $search_path = substr($search_path, 2);
        }
        if (file_exists($search_path.DIRECTORY_SEPARATOR.'application')
            && file_exists($search_path.DIRECTORY_SEPARATOR.'vendor')) {
            return realpath($search_path.DIRECTORY_SEPARATOR.'application');
        }
        if (DIRECTORY_SEPARATOR === $search_path || ++$count >= 16) {
            break;
        }
    } while ($search_path = dirname($search_path));

    exit('Unable to determine application path: search_path='.$search_path);
}
