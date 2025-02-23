<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
/**
 * Base Hazaar namespace.
 */

namespace Hazaar;

use Hazaar\Application\Config;
use Hazaar\Application\FilePath;
use Hazaar\Application\Request;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Exception\RouteNotFound;
use Hazaar\Application\Router\Exception\RouterInitialisationFailed;
use Hazaar\Application\URL;
use Hazaar\Controller\Response\File;
use Hazaar\File\Metric;
use Hazaar\Logger\Frontend;

require_once __DIR__.DIRECTORY_SEPARATOR.'Constants.php';

require_once __DIR__.DIRECTORY_SEPARATOR.'ErrorControl.php';

define('HAZAAR_VERSION', '3.0');
define('HAZAAR_START', microtime(true));

// Constant containing the application environment current being used.
defined('APPLICATION_ENV') || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development');

/**
 * The Application.
 *
 * The main application class is the core of the whole application and is responsible for routing actions
 * to controllers based on request objects extended extended from Hazaar\Application\Request.
 *
 * Supported request objects are:
 *
 * * Hazaar\Application\Request\Http - A standard HTTP request. This can be either GET or POST requests.
 * POST requests can optionally have a request body. In this case the Content-Type header will be checked
 * to see how to decode the request body. multipart/form-data and application/json are currently
 * accepted.
 * * Hazaar\Application\Request\Cli - This is a special request type to allow Hazaar applications to
 * be executed from the command line. This is currently used by the config tool
 *
 * ### Example usage:
 *
 * ```php
 * define('APPLICATION_ENV', 'development');
 * $config = 'application.ini';
 * $application = new Hazaar\Application(APPLICATION_ENV);
 * $application->bootstrap()->run();
 * ```
 */
class Application
{
    /**
     * The current version of Hazaar.
     */
    public const VERSION = '3.0';

    public ?Config $config = null;
    public ?Loader $loader = null;
    public ?Router $router = null;
    public string $environment = 'development';
    public string $path;
    public string $base;
    public ?Timer $timer = null;
    protected string $urlDefaultPart;
    private static ?Application $instance = null;
    private static string $root;

    /**
     * @var array<callable>
     */
    private array $outputFunctions = [];

    /**
     * The main application constructor.
     *
     * The application is basically the center of the Hazaar universe. Everything hangs off of it
     * and controllers are executed within the context of the application. The main constructor prepares
     * the application to begin processing and is the first piece of code executed within the Hazaar
     * environment.
     *
     * Because of this is it responsible for setting up the class loader, starting
     * code execution profiling timers, loading the application configuration and setting up the request
     * object.
     *
     * Hazaar also has a 'run direct' function that allows Hazaar to execute special requests directly
     * without going through the normal application execution path. These requests are used so hazaar
     * can serve static internal content such as error pages, images and style sheets required by
     * these error pages and redistributed JavaScript code libraries.
     *
     * @param string $env The application environment name. eg: 'development' or 'production'
     */
    public function __construct(string $env, ?string $path = null)
    {
        try {
            Application::$instance = $this;
            $this->environment = $env;
            $this->path = self::findApplicationPath($path);
            $this->base = dirname($_SERVER['SCRIPT_NAME']);
            // Create a timer for performance measuring
            $startTime = isset($_SERVER['REQUEST_TIME_FLOAT']) ? floatval($_SERVER['REQUEST_TIME_FLOAT']) : microtime(true);
            $this->timer = new Timer(5, $startTime);
            $this->timer->start('init', $startTime);
            // Create a loader object and register it as the default autoloader
            $this->loader = Loader::getInstance($this->path);
            $this->loader->register();
            // Store the search paths in the GLOBALS container so they can be used in config includes.
            Config::$overridePaths = self::getConfigOverridePaths();
            /*
             * Load it with a config object. if the file doesn't exist
             * it will just be an empty object that will handle calls to
             * it silently.
             */
            $config = Config::getInstance('application', $env, $this->getDefaultConfig());
            // Configure the application
            $this->configure($config);
            // Create a new router object for evaluating routes
            $routerConfig = $this->config['router'] ?? ['type' => 'file'];
            $routerConfig['applicationPath'] = $this->path;
            $this->router = new Router($routerConfig);
            $this->timer->stop('init');
        } catch (\Throwable $e) {
            dieDieDie($e);
        }
    }

    /**
     * The main application destructor.
     *
     * The destructor cleans up any application redirections. ifthe controller hasn't used it in this
     * run then it loses it. This prevents stale redirect URIs from accidentally being used.
     */
    public function shutdown(): void
    {
        if (!$this->config) {
            return;
        }
        $shutdown = $this->path.DIRECTORY_SEPARATOR.ake($this->config, 'app.files.shutdown', 'shutdown.php');
        if (file_exists($shutdown)) {
            include $shutdown;
        }
        if ($this->config['app']['metrics'] ?? false) {
            $metricFile = $this->getRuntimePath('metrics.dat');
            if ((!file_exists($metricFile) && is_writable(dirname($metricFile))) || is_writable($metricFile)) {
                $metric = new Metric($metricFile);
                if (!$metric->exists()) {
                    $metric->addDataSource('hits', 'COUNTER', null, null, 'Hit Counter');
                    $metric->addDataSource('exec', 'GAUGEZ', null, null, 'Execution Timer');
                    $metric->addDataSource('mem', 'GAUGE', null, null, 'Memory Usage');
                    $metric->addArchive('count_1hour', 'COUNT', 6, 60, 'Count per minute for last hour');
                    $metric->addArchive('avg_1hour', 'AVERAGE', 6, 60, 'Average per minute for last hour');
                    $metric->addArchive('count_1day', 'COUNT', 360, 24, 'Count per hour for last day');
                    $metric->addArchive('avg_1day', 'AVERAGE', 360, 24, 'Average per hour for last day');
                    $metric->addArchive('count_1week', 'COUNT', 360, 168, 'Count per hour for last week');
                    $metric->addArchive('avg_1week', 'AVERAGE', 360, 168, 'Average per hour for last week');
                    $metric->addArchive('count_1year', 'COUNT', 8640, 365, 'Count per day for last year');
                    $metric->addArchive('avg_1year', 'AVERAGE', 8640, 365, 'Average per day for last year');
                    $metric->create(10);
                }
                $metric->setValue('hits', 1);
                $metric->setValue('exec', (microtime(true) - HAZAAR_START) * 1000);
                $metric->setValue('mem', memory_get_peak_usage());
            }
        }
    }

    /**
     * @return array<string> $paths
     */
    public static function getConfigOverridePaths(): array
    {
        $paths = [
            'server'.DIRECTORY_SEPARATOR.ake($_SERVER, 'SERVER_NAME'),
            'host'.DIRECTORY_SEPARATOR.ake($_SERVER, 'HTTP_HOST'),
            // 'user'.DIRECTORY_SEPARATOR.APPLICATION_USER,
            'local',
        ];
        if ('cli' === \php_sapi_name()) {
            $paths[] = 'cli';
        }

        return $paths;
    }

    /**
     * @return array<mixed> $config
     */
    public function getDefaultConfig(): array
    {
        return [
            'app' => [
                'root' => ('cli-server' === php_sapi_name()) ? null : dirname($_SERVER['SCRIPT_NAME']),
                'errorController' => null,
                'favicon' => 'favicon.png',
                'timezone' => 'UTC',
                'polyfill' => true,
                'rewrite' => true,
                'files' => [
                    'bootstrap' => 'bootstrap.php',
                    'request' => 'request.php',
                    'complete' => 'complete.php',
                    'shutdown' => 'shutdown.php',
                    'route' => 'route.php',
                    'media' => 'media.php',
                ],
                'responseImageCache' => false,
                'runtimePath' => $this->path.DIRECTORY_SEPARATOR.'.runtime',
                'metrics' => false,
                'responseType' => 'html',
                'layout' => 'application',
            ],
            'router' => [
                'type' => 'basic',
                'controller' => 'index',
                'action' => 'index',
            ],
            'paths' => [
                'model' => 'models',
                'view' => 'views',
                'controller' => 'controllers',
                'service' => 'services',
                'helper' => 'helpers',
            ],
            'view' => [
                'prepare' => false,
            ],
        ];
    }

    public function configure(Config $config): void
    {
        if (!isset($config['app'])) {
            throw new \Exception('Invalid application configuration!');
        }
        $this->config = $config;
        if ($this->config['app']['alias'] ?? false) {
            URL::$aliases = $this->config['app']->getArray('alias');
        }
        Application::setRoot($this->config['app']['root'] ?? '/');
        // PHP root elements can be set directly with the PHP ini_set function
        if (isset($this->config['php'])) {
            $phpValues = array_to_dot_notation($this->config['php']);
            foreach ($phpValues as $directive => $phpValue) {
                ini_set($directive, $phpValue);
            }
        }
        // Check the load average and protect ifneeded
        if (isset($this->config['app']['maxload']) && function_exists('sys_getloadavg')) {
            $la = sys_getloadavg();
            if ($la[0] > $this->config['app']['maxload']) {
                throw new Application\Exception\ServerBusy();
            }
        }
        // Use the config to add search paths to the loader
        $this->loader->addSearchPaths($this->config['paths']);
        /*
         * Initialise any configured modules
         *
         * These modules may setup things to run in the background or make certain functions availble
         * and this is where we can start them up if they have valid configuration.
         */
        $initialisers = [
            'app' => '\Hazaar\Application\Url',
            'log' => '\Hazaar\Logger\Frontend',
        ];
        foreach ($initialisers as $property => $class) {
            if (isset($this->config[$property]) && class_exists($class)) {
                $moduleConfig = $this->config[$property];
                if (!is_array($moduleConfig)) {
                    throw new \Exception('Invalid configuration module: '.$property);
                }
                $class::initialise($moduleConfig);
            }
        }
    }

    /**
     * Get the current application instance.
     *
     * This static function can be used to get a reference to the current application instance from
     * anywhere.
     *
     * @return Application The application instance
     */
    public static function getInstance(): ?Application
    {
        if (Application::$instance) {
            return Application::$instance;
        }

        return null;
    }

    public static function setRoot(string $value): void
    {
        Application::$root = rtrim(str_replace('\\', '/', $value), '/').'/';
    }

    public static function getRoot(): string
    {
        return Application::$root;
    }

    /**
     * Returns the application runtime directory.
     *
     * The runtime directory is a place where Hazaar will keep files that it needs to create during
     * normal operation. For example, socket files for background scheduler communication, cached views,
     * and backend applications.
     *
     * @param string $suffix    An optional suffix to tack on the end of the path
     * @param bool   $createDir automatically create the runtime directory if it does not exist
     *
     * @return string The path to the runtime directory
     */
    public function getRuntimePath($suffix = null, $createDir = false): string
    {
        $path = $this->config['app']['runtimePath'] ?? '.runtime';
        if (!file_exists($path)) {
            $parent = dirname($path);
            if (!is_writable($parent)) {
                throw new Application\Exception\RuntimeDirUncreatable($path);
            }

            // Try and create the directory automatically
            try {
                @mkdir($path, 0775);
            } catch (\Exception $e) {
                throw new Application\Exception\RuntimeDirNotFound($path);
            }
        }
        if (!is_writable($path)) {
            throw new Application\Exception\RuntimeDirNotWritable($path);
        }
        $path = realpath($path);
        if (null === $suffix || !($suffix = trim($suffix))) {
            return $path;
        }
        if (DIRECTORY_SEPARATOR != substr($suffix, 0, 1)) {
            $suffix = DIRECTORY_SEPARATOR.$suffix;
        }
        $fullPath = $path.$suffix;
        if (!file_exists($fullPath) && $createDir) {
            mkdir($fullPath, 0775, true);
        }

        return $fullPath;
    }

    /**
     * Return the requested path in the current application.
     *
     * This method allows access to the raw URL path part, relative to the current application request.
     *
     * @param string $path          path suffix to append to the application path
     * @param bool   $forceRealpath Return the real path to a file.  If the file does not exist, this will return false.
     */
    public function getFilePath(?string $path = null, bool $forceRealpath = true): false|string
    {
        if (strlen($path) > 0) {
            $path = DIRECTORY_SEPARATOR.trim($path ?? '', DIRECTORY_SEPARATOR);
        }
        $path = $this->path.($path ? $path : null);
        if (true === $forceRealpath) {
            return realpath($path);
        }

        return $path;
    }

    /**
     * Get the currently requested controller name.
     *
     * @return string The current controller name
     */
    public function getRequestedController(): string
    {
        return ''; // $this->router->getController();
    }

    /**
     * Get the real path to the application on the local filesystem resolving links.
     *
     * @param string $suffix application path suffix
     *
     * @return string The resolved application path
     */
    public function getApplicationPath($suffix = null): string
    {
        return realpath($this->path.DIRECTORY_SEPARATOR.(string) $suffix);
    }

    /**
     * Get the base path.
     *
     * The base path is the root your application which contains the application, library and public
     * directories
     *
     * @param string $suffix application base path suffix
     *
     * @return string The resolved base path
     */
    public function getBasePath($suffix = null): string
    {
        return realpath($this->path.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.(string) $suffix);
    }

    /**
     * Initialise the application ready for execution.
     *
     * Bootstrap is the first step in running an application. It will run some checks to make sure
     * sure the server has any required modules loaded as requested by the application (via the config). It
     * will then execute the application bootstrap.php script within the context of the application. Once
     * that step succeeds the requested (or the default) controller will be loaded and initialised so that
     * it is ready for execution by the application.
     *
     * @return Application Returns a reference to itself to allow chaining
     */
    public function bootstrap(): Application
    {
        $this->timer->start('boot');
        set_error_handler([$this, 'errorHandler'], E_ERROR);
        set_exception_handler([$this, 'exceptionHandler']);
        register_shutdown_function([$this, 'shutdownHandler']);
        register_shutdown_function([$this, 'shutdown']);
        $locale = null;
        if (isset($this->config['app']['locale'])) {
            $locale = $this->config['app']['locale'];
        }
        if (false === setlocale(LC_ALL, $locale)) {
            throw new \Exception("Unable to set locale to {$locale}.  Make sure the {$locale} locale is enabled on your system.");
        }
        $tz = $this->config['app']['timezone'] ?? 'UTC';
        if (!date_default_timezone_set($tz)) {
            throw new Application\Exception\BadTimezone($tz);
        }
        $this->loader->addSearchPath(FilePath::RUNTIME, $this->getRuntimePath(null, true));
        if (false === $this->router->initialise()) {
            throw new RouterInitialisationFailed('Router returned false');
        }
        // Check for an application bootstrap file and execute it
        $bootstrapFile = $this->path
            .DIRECTORY_SEPARATOR
            .ake($this->config, 'app.files.bootstrap', 'bootstrap.php');
        if (file_exists($bootstrapFile)) {
            $config = $this->config;
            $router = $this->router;
            // @phpstan-ignore-next-line
            (function () use ($bootstrapFile, $config, $router) {
                include $bootstrapFile;
            })();
        }
        $this->timer->stop('boot');

        return $this;
    }

    /**
     * Executes the application.
     *
     * Once the application has been initialised and a controller loaded, it can be executed via
     * the run() method. This will execute the loaded controller and check that it returns a
     * valid [[Hazaar\Controller\Response]] object. ifa valid response is not returned an exception
     * will be raised.
     *
     * Once a valid response object is returned it will be used to write output back to the web server
     * to be returned to the user.
     *
     * This method also has protection against error loops. ifan exception is thrown while processing
     * a [[Hazaar\Controller\Error]] controller object then a new exception will be thrown outside the
     * application context and will display basic fall-back error output.
     *
     * @exception Application\Exception\ResponseInvalid if the controller returns an invalid response object
     */
    public function run(?Controller $controller = null): int
    {
        $code = 0;

        try {
            $this->timer->start('exec');
            ob_start();
            // Create the request object
            $request = new Request($_SERVER, $_REQUEST);
            $requestFile = $this->path
                .DIRECTORY_SEPARATOR
                .ake($this->config, 'app.files.request', 'request.php');
            if (file_exists($requestFile)) {
                ob_start();
                // @phpstan-ignore-next-line
                (function () use ($requestFile, $request) {
                    include $requestFile;
                })();
                ob_end_clean();
            }
            if (null !== $controller) {
                $response = $controller->initialize($request);
                if (null === $response) {
                    $response = $controller->run();
                }
            } else {
                $route = $this->router->evaluateRequest($request);
                if (!$route) {
                    throw new RouteNotFound($request->getPath());
                }
                $controller = $route->getController();
                $response = $controller->initialize($request);
                if (null === $response) {
                    $response = $controller->run($route);
                }
            }
            if (count($this->outputFunctions) > 0) {
                foreach ($this->outputFunctions as $func) {
                    $func($response);
                }
            }
            // Finally, write the response to the output buffer.
            $response->writeOutput();
            $this->timer->stop('exec');
            // Shutdown the controller
            $this->timer->start('shutdown');
            $controller->shutdown($response);
            $code = $response->getStatus();
            ob_end_flush();
            $completeFile = $this->path
                .DIRECTORY_SEPARATOR
                .ake($this->config, 'app.files.complete', 'complete.php');
            if (file_exists($completeFile)) {
                ob_start();
                $timer = $this->timer;
                // @phpstan-ignore-next-line
                (function () use ($completeFile, $request, $response, $controller, $timer) {
                    include $completeFile;
                })();
                ob_end_clean();
            }
        } catch (Controller\Exception\HeadersSent $e) {
            dieDieDie('HEADERS SENT');
        } catch (\Throwable $e) {
            /*
            * Here we check if the controller we tried to execute was already an error
            * if it is and we try and execute another error we could end up in an endless loop
            * so we throw a normal exception that will be grabbed by ErrorControl as an unhandled exception.
            */
            if ($controller instanceof Controller\Error) {
                dieDieDie($e->getMessage());
            } else {
                $this->exceptionHandler($e, isset($route) ? $route->getResponseType() : null);
            }
        }

        return $code;
    }

    /**
     * Return the requested path in the current application.
     *
     * This method allows access to the raw URL path part, relative to the current application request.
     */
    public static function getPath(?string $path = null): string
    {
        return Application::$root.($path ? trim($path, '/') : null);
    }

    /**
     * Generate a URL relative to the application.
     *
     * This is the base method for generating URLs in your application. URLs generated directly from here
     * are relative to the application base path. For URLs that are relative to the current controller see
     * Controller::url()
     *
     * Parameters are dynamic and depend on what you are trying to generate.
     *
     * For examples see: [Generating URLs](/guide/basics/urls.md)
     */
    public function getURL(): URL
    {
        $url = new URL();
        call_user_func_array([$url, '__construct'], func_get_args());

        return $url;
    }

    /**
     * Return the current Hazaar framework version.
     */
    public function getVersion(): string
    {
        return HAZAAR_VERSION;
    }

    /**
     * Set the response type override for a request.
     *
     * The response type should be set in the response object itself.  However, setting this allows that to be overridden.  This should
     * be used sparingly but can be used from a controller to force reponses to a certain type, such as *application/json*.
     */
    public function setResponseType(string $type): void
    {
        $this->config['app']['responseType'] = $type;
    }

    /**
     * Register an output function.
     *
     * @param array<object|string>|callable $function
     */
    public function registerOutputFunction(array|callable $function): void
    {
        $this->outputFunctions[] = $function;
    }

    /**
     * Custom error handler function.
     *
     * This function is responsible for handling PHP errors and displaying appropriate error messages.
     *
     * @param int         $errno   the error number
     * @param string      $errstr  the error message
     * @param null|string $errfile the file where the error occurred
     * @param null|int    $errline the line number where the error occurred
     *
     * @return bool returns true to prevent the default PHP error handler from being called
     */
    public function errorHandler(int $errno, string $errstr, ?string $errfile = null, ?int $errline = null): bool
    {
        if ($errno >= 500) {
            Frontend::e('CORE', "Error #{$errno} on line {$errline} of file {$errfile}: {$errstr}");
        }

        errorAndDie($errno, $errstr, $errfile, $errline, debug_backtrace());

        return true;
    }

    /**
     * Shutdown handler function.
     *
     * This function is responsible for executing the shutdown tasks registered in the global variable $__shutdownTasks.
     * It checks if the script is running in CLI mode or if headers have already been sent before executing the tasks.
     */
    public function shutdownHandler(): void
    {
        if (($error = error_get_last()) !== null) {
            if (ob_get_length() && 1 == ini_get('display_errors') && 'cli' !== php_sapi_name()) {
                ob_clean();
            }
            match ($error['type']) {
                E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR => errorAndDie($error, debug_backtrace()),
                default => null
            };
        }
    }

    /**
     * Exception handler function.
     *
     * This function is responsible for handling exceptions thrown in the application.
     * If the exception code is greater than or equal to 500, it logs the error message
     * along with the code, line number, and file name. Then it calls the `errorAndDie()`
     * function to handle the error further.
     */
    public function exceptionHandler(\Throwable $e, ?int $responseType = null): void
    {
        if ($e->getCode() >= 500) {
            Frontend::e('CORE', 'Error #'.$e->getCode().' on line '.$e->getLine().' of file '.$e->getFile().': '.$e->getMessage());
        }
        errorAndDie($e, $responseType);
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public static function findApplicationPath(?string $search_path = null): false|string
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
                && file_exists($search_path.DIRECTORY_SEPARATOR.'application'.DIRECTORY_SEPARATOR.'configs')) {
                return realpath($search_path.DIRECTORY_SEPARATOR.'application');
            }
            if (DIRECTORY_SEPARATOR === $search_path || ++$count >= 16) {
                break;
            }
        } while ($search_path = dirname($search_path));

        return false;
    }
}
