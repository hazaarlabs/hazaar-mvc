<?php

/**
 * @file        Hazaar/Application.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

/**
 * Base Hazaar namespace
 */
namespace Hazaar;

define('HAZAAR_VERSION', '3.0');

/**
 * Constant containing the application environment current being used.
 */
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development'));

/**
 * Constant containing the detected 'name' of the application.
 *
 * Essentially this is the name of the directory the application is stored in.
 */
define('APPLICATION_NAME', array_values(array_slice(explode(DIRECTORY_SEPARATOR, realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..')), -1))[0]);

putenv('HOME=' . APPLICATION_PATH);

/**
 * Change the current working directory to the application path so that all paths are relative to it.
 */
chdir(APPLICATION_PATH);

/**
 * The Application
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
 * $application = new Hazaar\Application(APPLICATION_ENV, $config);
 * $application->bootstrap()->run();
 * ```
 */
class Application {

    public $GLOBALS = [
        'hazaar' => ['exec_start' => HAZAAR_EXEC_START, 'version' => HAZAAR_VERSION],
        'env' => APPLICATION_ENV,
        'path' => APPLICATION_PATH,
        'base' => APPLICATION_BASE,
        'name' => APPLICATION_NAME
    ];

    public $request;

    public $response = 'no output';

    public $config;

    public $loader;

    public $router;

    public $environment = 'development';

    public $timer;

    public $bootstrap;

    private static $instance;

    private static $root;

    private $protocol;

    protected $response_type = null;

    private $metrics = false; //Internal metrics settings.  This is disabled when using the console.

    /**
     * The main application constructor
     *
     * The application is basically the center of the Hazaar MVC universe. Everything hangs off of it
     * and controllers are executed within the context of the application. The main constructor prepares
     * the application to begin processing and is the first piece of code executed within the HazaarMVC
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
     * @since 1.0.0
     *
     * @param string $env The application environment name. eg: 'development' or 'production'
     */
    function __construct($env) {

        try{

            Application::$instance = $this;

            $this->environment = $env;

            if(!defined('HAZAAR_INIT_START'))
                define('HAZAAR_INIT_START', microtime(TRUE));

            /*
            * Create a loader object and register it as the default autoloader
            */
            $this->loader = Loader::getInstance($this);

            $this->loader->register();

            //Store the search paths in the GLOBALS container so they can be used in config includes.
            $this->GLOBALS['paths'] = $this->loader->getSearchPaths();
        
            Application\Config::$override_paths = self::getConfigOverridePaths();

            /*
             * Load it with a config object. if the file doesn't exist
             * it will just be an empty object that will handle calls to
             * it silently.
             */
            $config = new Application\Config('application', $env, $this->getDefaultConfig(), FILE_PATH_CONFIG);

            if(!$config->loaded())
                dieDieDie('Application is not configured!');

            if($config->app['polyfill'] === true)
                require_once('Polyfill.php');

            /*
             * Check if we require SSL and don't have and if so, redirect here.
             */
            if($config->app->has('require_ssl') && boolify($_SERVER['HTTPS']) !== boolify($config->app->require_ssl)){

                header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);

                exit;

            }

            /*
             * Configure the application
             */
            $this->configure($config);

            /*
             * Create the request object
             */
            $this->request = Application\Request\Loader::load();

            /*
             * Create a new router object for evaluating routes
             */
            $this->router = new Application\Router($this->config);

        }catch(\Throwable $e){
            
            dieDieDie($e);

        }

    }

    static public function getConfigOverridePaths(){

        $paths = [
            'server' . DIRECTORY_SEPARATOR . ake($_SERVER, 'SERVER_NAME'),
            'host' . DIRECTORY_SEPARATOR . ake($_SERVER, 'HTTP_HOST'),
            'user' . DIRECTORY_SEPARATOR . APPLICATION_USER,
            'local'
        ];

        if(\php_sapi_name() === 'cli')
            $paths[] = 'cli';

        return $paths;

    }

    /**
     * The main application destructor
     *
     * The destructor cleans up any application redirections. ifthe controller hasn't used it in this
     * run then it loses it. This prevents stale redirect URIs from accidentally being used.
     *
     * @since 1.0.0
     *
     */
    function __destruct() {

        if($this->config){

            $shutdown = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($this->config->app->files, 'shutdown', 'shutdown.php');

            if(file_exists($shutdown))
                include ($shutdown);

            if($this->metrics === true){

                $metric_file = $this->runtimePath('metrics.dat');

                $metric = new \Hazaar\File\Metric($metric_file);

                if(!$metric->exists()){

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

                $metric->setValue('exec', (microtime(true) - HAZAAR_EXEC_START) * 1000);

                $metric->setValue('mem', memory_get_peak_usage());

            }

        }

        if($this->request){

            \Hazaar\Logger\Frontend::i('CORE', '"' . ake($_SERVER, 'REQUEST_METHOD') . ' /' .  $this->request->getBasePath() . '" ' 
                . http_response_code()
                . ' "' . ake($_SERVER, 'HTTP_USER_AGENT') . '"'
            ); 

        }

    }

    public function getDefaultConfig(){

        return [
            'app' => [
                'root' => (php_sapi_name() === 'cli-server') ? null : dirname($_SERVER['SCRIPT_NAME']),
                'defaultController' => 'Index',
                'errorController' => null,
                'useDefaultController' => false,
                'favicon' => 'favicon.png',
                'timezone' => 'UTC',
                'polyfill' => true,
                'rewrite' => true,
                'files' => [
                    'bootstrap' => 'bootstrap.php',
                    'shutdown' => 'shutdown.php',
                    'route' => 'route.php',
                    'media' => 'media.php'
                ],
                'responseImageCache' => false,
                'runtimepath' => APPLICATION_PATH . DIRECTORY_SEPARATOR . '.runtime'
            ],
            'paths' => [
                'model' => 'models',
                'view' => 'views',
                'controller' => 'controllers',
                'service' => 'services',
                'helper' => 'helpers'
            ],
            'view' => [
                'prepare' => false
            ]
        ];

    }

    public function configure(\Hazaar\Map $config){

        if(!$config->has('app'))
            throw new \Exception('Invalid application configuration!');

        $this->config = $config;

        if($this->config->app->has('alias'))
            Application\Url::$__aliases = $this->config->app->getArray('alias');

        //Allow the root to be configured but the default absolutely has to be set so here we double
        $this->config->app->addInputFilter(function($value){
            Application::setRoot($value);
        }, 'root');

        Application::setRoot($this->config->app['root']);

        /*
         * PHP root elements can be set directly with the PHP ini_set function
         */
        if($this->config->has('php')){

            $php_values = $this->config->php->toDotNotation()->toArray();

            foreach($php_values as $directive => $php_value)
                ini_set($directive, $php_value);

        }

        /*
         * Check the load average and protect ifneeded
         */
        if($this->config->app->has('maxload') && function_exists('sys_getloadavg')) {

            $la = sys_getloadavg();

            if($la[0] > $this->config->app['maxload'])
                throw new Application\Exception\ServerBusy();

        }

        /*
         * Use the config to add search paths to the loader
         */
        $this->loader->addSearchPaths($this->config->get('paths'));

        /*
         * Initialise any configured modules
         * 
         * These modules may setup things to run in the background or make certain functions availble
         * and this is where we can start them up if they have valid configuration.
         */
        $initialisers = [
            'app'  => '\Hazaar\Application\Url',
            'log'  => '\Hazaar\Logger\Frontend'
        ];

        foreach($initialisers as $property => $class){

            if($this->config->has($property) && class_exists($class)){

                $module_config = $this->config->get($property);

                if(!$module_config instanceof \Hazaar\Map)
                    throw new \Exception('Invalid configuration module: ' . $property);

                $class::initialise($module_config);

            }

        }
             
        
        /*
         * Create a timer for performance measuring
         */
        if($this->config->app->has('timer') && $this->config->app['timer'] == TRUE) {

            $this->timer = new Timer();

            $this->timer->start('init', HAZAAR_INIT_START);

            $this->timer->stop('init');

        }
        
    }

    /**
     * Get the current application instance
     *
     * This static function can be used to get a reference to the current application instance from
     * anywhere.
     *
     * @since 1.0.0
     *
     * @return \Hazaar\Application The application instance
     */
    static public function getInstance() {

        if(Application::$instance instanceof Application)
            return Application::$instance;

        return false;

    }

    static public function setRoot($value){

        Application::$root = rtrim(str_replace('\\', '/', $value), '/') . '/';

    }

    static public function getRoot(){

        return Application::$root;

    }

    /**
     * Returns the application runtime directory
     *
     * The runtime directory is a place where HazaarMVC will keep files that it needs to create during
     * normal operation. For example, socket files for background scheduler communication, cached views,
     * and backend applications.
     *
     * @param string $suffix An optional suffix to tack on the end of the path
     * @param boolean $create_dir Automatically create the runtime directory if it does not exist.
     *
     * @since 1.0.0
     *
     * @return string The path to the runtime directory
     */
    public function runtimePath($suffix = NULL, $create_dir = FALSE) {

        $path = $this->config->app->get('runtimepath');

        if(!file_exists($path)) {

            $parent = dirname($path);

            if(!is_writable($parent))
                throw new Application\Exception\RuntimeDirUncreatable($path);

            // Try and create the directory automatically
            try {

                @mkdir($path, 0775);

            }
            catch(\Exception $e) {

                throw new Application\Exception\RuntimeDirNotFound($path);

            }

        }

        if(!is_writable($path))
            throw new Application\Exception\RuntimeDirNotWritable($path);

        $path = realpath($path);

        if($suffix === null || !($suffix = trim($suffix)))
            return $path;

        if($suffix && substr($suffix, 0, 1) != DIRECTORY_SEPARATOR)
            $suffix = DIRECTORY_SEPARATOR . $suffix;

        $full_path = $path . $suffix;

        if(!file_exists($full_path) && $create_dir)
            mkdir($full_path, 0775, TRUE);

        return $full_path;

    }

    /**
     * Return the requested path in the current application
     *
     * This method allows access to the raw URL path part, relative to the current application request.
     *
     * @since 1.0.0
     * 
     * @param string $path Path suffix to append to the application path.
     * @param boolean $force_realpath Return the real path to a file.  If the file does not exist, this will return false.
     */
    static public function filePath($path = NULL, $force_realpath = TRUE) {

        if(strlen($path) > 0)
            $path = DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR);

        $path = APPLICATION_PATH . ($path ? $path : NULL);

        if($force_realpath === true)
            return realpath($path);

        return $path;

    }

    /**
     * Get the currently requested controller name
     *
     * @return string The current controller name
     */
    public function getRequestedController() {

        return $this->router->getController();

    }

    /**
     * Get the real path to the application on the local filesystem resolving links
     *
     * @since 1.0.0
     *
     * @param string $suffix Application path suffix.
     * 
     * @return string The resolved application path
     */
    public function getApplicationPath($suffix = null) {

        return realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . (string)$suffix);

    }

    /**
     * Get the base path
     *
     * The base path is the root your application which contains the application, library and public
     * directories
     *
     * @since 1.0.0
     *
     * @param string $suffix Application base path suffix.
     * 
     * @return string The resolved base path
     */
    public function getBasePath($suffix = null) {

        return realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . (string)$suffix);

    }

    /**
     * Initialise the application ready for execution
     *
     * Bootstrap is the first step in running an application. It will run some checks to make sure
     * sure the server has any required modules loaded as requested by the application (via the config). It
     * will then execute the application bootstrap.php script within the context of the application. Once
     * that step succeeds the requested (or the default) controller will be loaded and initialised so that
     * it is ready for execution by the application.
     *
     * @since 1.0.0
     *
     * @return \Hazaar\Application Returns a reference to itself to allow chaining
     *
     */
    public function bootstrap() {

        try{

            if($this->timer) {

                $this->timer->start('_bootstrap', HAZAAR_EXEC_START);

                $this->timer->stop('_bootstrap');

                $this->timer->start('bootstrap');

            }

            $locale = NULL;

            if($this->config->app->has('locale'))
                $locale = $this->config->app['locale'];

            //Fix locales on windows
            if(substr(PHP_OS, 0, 3) == 'WIN'){

                //Remove any charset specs
                if(strpos($locale, '.'))
                    $locale = explode('.', $locale, 2)[0];

                //Change underscroes to hyphens and set lowercase
                $locale = strtolower(str_replace('_', '-', $locale));

            }

            if(setlocale(LC_ALL, $locale) === FALSE)
                throw new \Hazaar\Exception("Unable to set locale to $locale.  Make sure the $locale locale is enabled on your system.");

            $tz = $this->config->app->has('timezone') ? $this->config->app->timezone : 'UTC';

            if(!date_default_timezone_set($tz))
                throw new Application\Exception\BadTimezone($tz);

            if(!$this->router->evaluate($this->request))
                throw new Application\Exception\RouteNotFound($this->request->getPath());

            if($this->router->getController() !== 'hazaar') {

                if(!defined('RUNTIME_PATH')){

                    define('RUNTIME_PATH', $this->runtimePath(null, true));
        
                    $this->GLOBALS['runtime'] = RUNTIME_PATH;
        
                }
                
                /*
                * Check that all required modules are loaded
                */
                if($this->config->module->has('require')
                    && count($missing = array_diff($this->config->module['require']->toArray(), get_loaded_extensions())) > 0)
                    throw new Application\Exception\ModuleMissing($missing);

                /*
                * Check for an application bootstrap file and execute it
                */
                $bootstrap = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($this->config->app->files, 'bootstrap', 'bootstrap.php');

                if(file_exists($bootstrap)) {

                    $this->bootstrap = include($bootstrap);

                    if($this->bootstrap === FALSE)
                        throw new \Hazaar\Exception('The application failed to start!');

                }

            }

            if($this->timer)
                $this->timer->stop('bootstrap');

        }catch(\Throwable $e){

            dieDieDie($e);

        }

        return $this;

    }

    /**
     * Executes the application
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
     * @since 1.0.0
     *
     * @exception Application\Exception\ResponseInvalid if the controller returns an invalid response object
     */
    public function run(Controller $controller = NULL) {

        $code = 0;

        try {

            if($this->timer){

                $this->timer->start('_exec', HAZAAR_EXEC_START);
    
                $this->timer->stop('_exec');
    
                $this->timer->start('exec');
    
            }

            if(!$controller instanceof Controller) {

                /*
                 * Load the controller and check it was successful
                 */
                $controller = $this->loader->loadController($this->router->getController(), $this->router->getControllerName());

                if(!($controller instanceof Controller))
                    throw new Application\Exception\RouteNotFound($this->request->getBasePath());

            }

            $controller->setApplication($this);

            if($this->config->app['metrics'] === true)
                $this->metrics = $controller->use_metrics;

            $this->url_default_part = $controller->url_default_action_name;

            /*
             * Initialise the controller with the current request
             */
            $response = $controller->__initialize($this->request);

            if(is_int($response)){

                return $response;

            //If we get a response now, the controller wants out, so display it and quit.
            }elseif($response instanceof \Hazaar\Controller\Response){ 

                $response->__writeOutput();

                $controller->__shutdown($response);

                return $code;

            }

            /*
             * Execute the controllers run method.
             */
            $this->response = $controller->__run();

            if(is_int($this->response)){

                $code = $this->response;

            }else{

                if(!$this->response instanceof \Hazaar\Controller\Response)
                    $this->response = new \Hazaar\Controller\Response\HTTP\NoContent();

                if(!$this->response->hasController())
                    $this->response->setController($controller);

                /*
                * The run method should have returned a response object that we can output to the client
                */
                if(!($this->response instanceof Controller\Response))
                    throw new Application\Exception\ResponseInvalid();

                /*
                * If the controller has specifically requested a return status code, set it now.
                */
                if($status = $controller->getStatus())
                    $this->response->setStatus($status);

                $this->response->setCompression($this->config->app->get('compress', false));
                
                /*
                * Finally, write the response to the output buffer.
                */
                $this->response->__writeOutput();

                /*
                * Shutdown the controller
                */
                $controller->__shutdown($this->response);

                if($this->timer) {

                    $this->timer->start('shutdown');
        
                    $this->timer->stop('exec');
        
                }

                $code = $this->response->getStatus();

            }

        }catch(Controller\Exception\HeadersSent $e) {

            dieDieDie("HEADERS SENT");

        }catch(\Exception $e) {

            /*
            * Here we check if the controller we tried to execute was already an error
            * if it is and we try and execute another error we could end up in an endless loop
            * so we throw a normal exception that will be grabbed by ErrorControl as an unhandled exception.
            */
            if($controller instanceof Controller\Error)
                dieDieDie($e->getMessage());

            else
                throw $e;

        }

        return $code;

    }


    /**
     * Return the requested path in the current application
     *
     * This method allows access to the raw URL path part, relative to the current application request.
     *
     * @since 1.0.0
     */
    static public function path($path = NULL) {

        return Application::$root . ($path ? trim($path, '/') : NULL);

    }

    /**
     * Generate a URL relative to the application
     *
     * This is the base method for generating URLs in your application. URLs generated directly from here
     * are relative to the application base path. For URLs that are relative to the current controller see
     * Controller::url()
     *
     * Parameters are dynamic and depend on what you are trying to generate.
     *
     * For examples see: [Generating URLs](/basics/urls.md)
     */
    public function url() {

        $url = new Application\Url();

        call_user_func_array([$url, '__construct'], func_get_args());

        return $url;

    }

    /**
     * Test if a URL is active, relative to the application base URL.
     *
     * Parameters are simply a list of URL 'parts' that will be combined to test against the current URL to see if it is active.  Essentially
     * the argument list is the same as `Hazaar\Application::url()` except that parameter arrays are not supported.
     * 
     * Unlike `Hazaar\Controller::active()` this method tests if the path is active relative to the application base path.  If you
     * want to test if a particular controller is active, then it has to be the first argument.
     * 
     * * Example
     * ```php
     * $application->active('mycontroller');
     * ```
     * 
     * @return boolean True if the supplied URL is active as the current URL.
     */
    public function active() {

        $parts = [];

        foreach(func_get_args() as $part){

            $part_parts = strpos($part, '/') ? array_map('strtolower', array_map('trim', explode('/', $part))) : [$part];

            foreach($part_parts as $part_part)
                $parts[] = strtolower(trim($part_part));

        }

        if(!($base_path = $this->request->getBasePath()))
            $base_path = strtolower(Application\Url::$__default_controller);

        $request_parts = $base_path ? array_map('strtolower', array_map('trim', explode('/', $base_path))) : [];

        for($i = 0; $i < count($parts); $i++){

            if(!array_key_exists($i, $request_parts) && $this->url_default_part !== null)
                $request_parts[$i] = $this->url_default_part;

            if($parts[$i] !== $request_parts[$i])
                return false;

        }

        return true;

    }

    /**
     * Generate a redirect response to redirect the browser
     *
     * It's quite common to redirect the user to an alternative URL. This may be to forward the request
     * to another website, forward them to an authentication page or even just remove processed request
     * parameters from the URL to neaten the URL up.
     *
     * @since 1.0.0
     *
     * @param string $location  The URI you want to redirect to
     * @param boolean $save_uri Optionally save the URI so we can redirect back. See: `Hazaar\Application::redirectBack()`
     */
    public function redirect($location, $save_uri = false) {

        if(!$location)
            return false;

        $headers = apache_request_headers();

        if(array_key_exists('X-Requested-With', $headers) && $headers['X-Requested-With'] === 'XMLHttpRequest') {

            echo "<script>document.location = '$location';</script>";

        } else {

            $sess = new \Hazaar\Session();

            if($sess->has('REDIRECT') && $sess['REDIRECT'] === $location)
                unset($sess['REDIRECT']);

            if($save_uri) {

                $data = [
                    'URI' => $_SERVER['REQUEST_URI'],
                    'METHOD' => $_SERVER['REQUEST_METHOD']
                ];

                if($_SERVER['REQUEST_METHOD'] === 'POST')
                    $data['POST'] = $_POST;

                $sess['REDIRECT'] = $data;

            }

        }

        return new \Hazaar\Controller\Response\HTTP\Redirect($location);

    }

    /**
     * Redirect back to a URI saved during redirection
     *
     * This mechanism is used with the $save_uri parameter of `Hazaar\Application::redirect()` so save the current
     * URI into the session so that once we're done processing the request somewhere else we can come back
     * to where we were. This is useful for when a user requests a page but isn't authenticated, we can
     * redirect them to a login page and then that page can call this `Hazaar\Application::redirectBack()` method to redirect the
     * user back to the page they were originally looking for.
     *
     * @since 1.0.0
     */
    public function redirectBack() {

        $sess = new \Hazaar\Session();

        if(!($sess->has('REDIRECT') && ($uri = trim(ake($sess['REDIRECT'], 'URI')))))
            return false;

        if(ake($sess['REDIRECT'], 'METHOD') === 'POST') {

            if(substr($uri, -1, 1) !== '?')
                $uri .= '?';
            else
                $uri .= '&';

            $uri .= http_build_query(ake($sess['REDIRECT'], 'POST'));

        }

        unset($sess['REDIRECT']);

        return new \Hazaar\Controller\Response\HTTP\Redirect($uri);

    }

    /**
     * Return the current Hazaar MVC framework version.
     *
     * @return string
     */
    public function version() {

        return HAZAAR_VERSION;

    }

    /**
     * Returns the requested response type.
     *
     * The requested response type can be set in the request itself.  If it is not set, then the default will be 'html'
     * or the X-Requested-With header will be checked to determine the response type.
     *
     * This method is used internally to determine the response type to send when one has not been explicitly used.  Normally
     * the response type is determined by the Controller\Response object type returned by a controller action.
     *
     * @return string
     */
    public function getResponseType(){

        return $this->response_type;

    }

    /**
     * Set the response type override for a request.
     * 
     * The response type should be set in the response object itself.  However, setting this allows that to be overridden.  This should
     * be used sparingly but can be used from a controller to force reponses to a certain type, such as *application/json*.
     */
    public function setResponseType($type){

        $this->response_type = $type;

    }

    /**
     * Get the contents for the applications composer.json file
     * 
     * This is shorthand method to quickly get the application composer file.
     * 
     * @return boolean|\stdClass
     */
    public function composer(){

        if(!($path = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'composer.json')))
            return false;

        return json_decode(file_get_contents($path));

    }

}
