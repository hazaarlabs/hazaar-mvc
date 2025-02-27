<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Loader.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright(c)2012 Jamie Carl(http://www.hazaar.io)
 */

namespace Hazaar;

use Hazaar\Application\FilePath;

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
    private string $applicationPath;

    /**
     * @var array<Loader> an array of loader instances
     */
    private static array $instances = [];

    /**
     * Initialise a new loader.
     *
     * !!! warning
     * Do NOT instantiate this class directly. See Loader::getInstance() on how to get a new Loader instance.
     */
    protected function __construct(string $applicationPath)
    {
        $this->applicationPath = $applicationPath;
        $rootPath = dirname($applicationPath);
        // Add some default search paths
        // The root path is the root of the project that contains the application, public, db and other directories.
        $this->addSearchPath(FilePath::ROOT, $rootPath);
        // The application path is the path that contains the application files.
        $this->addSearchPath(FilePath::APPLICATION, $applicationPath);
        // The config path is the path that contains the configuration files for the application.
        $this->addSearchPath(FilePath::CONFIG, $applicationPath.'/configs');
        // The public path is the path that contains the public files and entry point for the application.
        $this->addSearchPath(FilePath::PUBLIC, $rootPath.'/public');
        // The db path is the path that contains the database files for the application.
        $this->addSearchPath(FilePath::DB, $rootPath.'/db');
        // The support path is the path that contains library support files for the application.
        $this->addSearchPath(FilePath::SUPPORT, __DIR__.'/../libs');
    }

    /**
     * Return the current instance of the Loader object.
     */
    public static function getInstance(string $path): Loader
    {
        if (isset(Loader::$instances[$path])) {
            return Loader::$instances[$path];
        }

        return Loader::$instances[$path] = new Loader($path);
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
     * * FilePath::ROOT - Path that contains the whole project
     * * FilePath::MODEL - Path contains model classes
     * * FilePath::VIEW - Path contains view files.
     * * FilePath::CONTROLLER - Path contains controller classes.
     * * FilePath::SUPPORT - Path contains support files. Used by the Application::runDirect()method.
     * * FilePath::CONFIG - Configuration files
     *
     * @param FilePath $type the path type to add
     * @param string   $path the path to add
     */
    public function addSearchPath(FilePath $type, string $path): void
    {
        $typeName = $type->value;
        if (!array_key_exists($typeName, $this->paths) || !in_array($path, $this->paths[$typeName])) {
            $this->paths[$typeName][] = $path;
        }
    }

    /**
     * Sets the search path for a file type.
     *
     * This is the same as addSearchPath except that it overwrites any existing paths.
     *
     * @param mixed $type the path type to add
     * @param mixed $path the path to add
     */
    public function setSearchPath($type, $path): void
    {
        $this->paths[$type] = [];
        $this->addSearchPath($type, $path);
    }

    /**
     * Add multiple search paths from an array.
     *
     * @param array<mixed> $array Array containing type/path pairs
     */
    public function addSearchPaths(array $array): void
    {
        foreach ($array as $typeName => $path) {
            $type = FilePath::tryFrom($typeName);
            if (!$type) {
                continue;
            }
            $this->addSearchPath($type, $this->applicationPath.DIRECTORY_SEPARATOR.$path);
        }
    }

    /**
     * Return an array of search paths for this loader instance.
     *
     * @return array<mixed> Array of search paths
     */
    public function getSearchPaths(?FilePath $type = null): ?array
    {
        if (!$type) {
            return $this->paths;
        }
        if (array_key_exists($type->value, $this->paths)) {
            return $this->paths[$type->value];
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
     * @param FilePath $type       The path type to search. See Loader::addSearchPath()
     * @param string   $searchFile The file to search for
     *
     * @return string The absolute path to the file if it exists. NULL otherwise.
     */
    public static function getFilePath(FilePath $type, ?string $searchFile = null): ?string
    {
        $app = Application::getInstance();
        $loader = $app ? $app->loader : Loader::getInstance(Application::findApplicationPath());
        // If the search file is an absolute path just return it if it exists.
        if ($searchFile && Loader::isAbsolutePath($searchFile)) {
            return realpath($searchFile);
        }
        if ($paths = $loader->getSearchPaths($type)) {
            foreach ($paths as $path) {
                if (!$searchFile) {
                    return $path;
                }
                $filename = $path.DIRECTORY_SEPARATOR.$searchFile;
                if ($realPath = realpath($filename)) {
                    return $realPath;
                }
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
     * Loads a class file based on the provided class name.
     *
     * This method splits the class name into parts using non-word characters and underscores as delimiters.
     * It then checks if the first part of the class name is 'Application', which acts as a namespace key
     * to restrict the loadable path to that of the application itself.
     * If the prefix is 'Application', it constructs the file path from the class name parts and attempts
     * to load the file.
     *
     * @param string $className the fully qualified name of the class to load
     */
    public static function loadClassFromFile($className): void
    {
        $namepath = preg_split('/(\W|_)/', $className, -1, PREG_SPLIT_NO_EMPTY);
        /*
         * Check that the prefix is 'Application'. This is sort of a namespace 'key' if you will
         * to restrict the loadable path to that of the application itself.
         */
        if ('Application' !== $namepath[0]) {
            return;
        }
        $filename = implode(DIRECTORY_SEPARATOR, array_slice($namepath, 2)).'.php';
        $type = FilePath::fromApplicationNamespace($namepath[1]);
        if (!$type) {
            return;
        }
        if ($fullPath = Loader::getFilePath($type, $filename)) {
            require_once $fullPath;
        }
    }
}
