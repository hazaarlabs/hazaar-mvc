<?php

/**
 * @file        Hazaar/Application.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief Base Hazaar namespace
 */
namespace Hazaar;

define('HAZAAR_EXEC_START', microtime(TRUE));

define('HAZAAR_VERSION', '2.0.0');

require_once ('Loader.php');

require_once (LIBRARY_PATH . '/Hazaar/Core/HelperFunctions.php');

/**
 * @brief The Application
 *
 * @detail The main application class is the core of the whole application and is responsible for routing actions
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
 * h3. Example usage:
 *
 * <code>
 * define('APPLICATION_ENV', 'development');
 * $config = 'application.ini';
 * $application = new Hazaar\Application(APPLICATION_ENV, $config);
 * $application->bootstrap()->run();
 * </code>
 */
class Application {

    public $request;

    public $response = 'no output';

    public $config;

    public $loader;

    public $environment = 'development';

    public $timer;

    public $bootstrap;

    private static $instance;

    /**
     * @brief The main application constructor
     *
     * @detail The application is basically the center of the Hazaar MVC universe. Everything hangs off of it
     * and controllers are executed within the context of the application. The main constructor prepares
     * the application to begin processing and is the first piece of code executed within the HazaarMVC
     * environment.
     *
     * Because of this is it responsible for setting up the class loader, logging, starting
     * code execution profiling timers, loading the application configuration and setting up the request
     * object.
     *
     * Hazaar also has a 'run direct' function that allows Hazaar to execute special requests directly
     * without going through the normal application execution path. These requests are used so hazaar
     * can serve static internal content such as error pages, images and style sheets required by
     * these error pages and redistributed JavaScript code libraries such as jQuery and KendoUI.
     *
     * @since 1.0.0
     *       
     * @param string $env
     *            The application environment name. eg: 'development' or 'production'
     *            
     * @param string $configFile
     *            The application configuration file to use. eg: application.ini
     */
    function __construct($env, $configFile) {

        $this->environment = $env;
        
        if (!defined('HAZAAR_INIT_START'))
            define('HAZAAR_INIT_START', microtime(TRUE));
        
        /**
         * Change the current working directory to the application path so that all paths are relative to it.
         */
        chdir(APPLICATION_PATH);
        
        /*
         * Create a loader object and register it as the default autoloader
         */
        $this->loader = Loader::getInstance($this);
        
        $this->loader->register();
        
        /*
         * Set up error control
         */
        require_once ('Hazaar/Core/ErrorControl.php');
        
        Application::$instance = $this;
        
        /**
         * Set up some default config properties.
         */
        $defaults = array(
            'app' => array(
                'defaultController' => 'Index',
                'favicon' => 'favicon.png',
                'timezone' => 'UTC'
            ),
            'paths' => array(
                'model' => 'models',
                'view' => 'views',
                'controller' => 'controllers'
            )
        );
        
        /*
         * Load it with a config object. If the file doesn't exist
         * it will just be an empty object that will handle calls to
         * it silently.
         */
        $this->config = new Application\Config($configFile, $env, $defaults);
        
        /**
         * Check the load average and protect if needed
         */
        if ($this->config->app->has('maxload') && function_exists('sys_getloadavg')) {
            
            $la = sys_getloadavg();
            
            if ($la[0] > $this->config->app['maxload']) {
                
                throw new Application\Exception\ServerBusy();
            }
        }
        
        /*
         * Create a timer for performance measuring
         */
        if ($this->config->app->has('timer') && $this->config->app['timer'] == TRUE) {
            
            $this->timer = new Timer();
            
            $this->timer->start('init', HAZAAR_INIT_START);
            
            $this->timer->stop('init');
        }
        
        $locale = NULL;
        
        if ($this->config->app->has('locale')) {
            
            $locale = $this->config->app['locale'];
        }
        
        if (setlocale(LC_ALL, $locale) === FALSE) {
            
            die("Unable to set locale to $locale.  Make sure the $locale locale is enabled on your system.");
        }
        
        if ($this->config->app->has('timezone')) {
            
            $tz = $this->config->app->timezone;
        } else {
            
            $tz = 'UTC';
        }
        
        if (!date_default_timezone_set($tz)) {
            
            throw new Application\Exception\BadTimezone($tz);
        }
        
        $this->request = Application\Request\Loader::load($this->config);
        
        /*
         * Use the config to add search paths to the loader
         */
        $this->loader->addSearchPaths($this->config->get('paths'));
    
    }

    /**
     * @brief The main application destructor
     *
     * @detail The destructor cleans up any application redirections. If the controller hasn't used it in this
     * run then it loses it. This prevents stale redirect URLs from accidentally being used.
     *
     * @since 1.0.0
     *       
     */
    function __destruct() {

        $shutdown = APPLICATION_PATH . '/shutdown.php';
        
        if (file_exists($shutdown)) {
            
            include ($shutdown);
        }
    
    }

    /**
     * @brief Get the current application instance
     *
     * @detail This static function can be used to get a reference to the current application instance from
     * anywhere.
     *
     * @since 1.0.0
     *       
     * @return \Hazaar\Application The application instance
     */
    static public function &getInstance() {

        return Application::$instance;
    
    }

    /**
     * @brief Returns the application runtime directory
     *
     * @detail The runtime directory is a place where HazaarMVC will keep files that it needs to create during
     * normal operation. For example, socket files for background scheduler communication, cached views,
     * and backend applications.
     *
     * @var string $suffix An optional suffix to tack on the end of the path
     *     
     * @since 1.0.0
     *       
     * @return string The path to the runtime directory
     */
    public function runtimePath($suffix = NULL, $create_dir = FALSE) {

        $path = APPLICATION_PATH . '/' . ($this->config->app->has('runtimepath') ? $this->config->app->runtimepath : '.runtime');
        
        if (!file_exists($path)) {
            
            $parent = dirname($path);
            
            if (!is_writable($parent))
                throw new Application\Exception\RuntimeDirUncreatable($path);
                
                // Try and create the directory automatically
            try {
                
                mkdir($path, 0775);
            } catch(\Exception $e) {
                
                throw new Application\Exception\RuntimeDirNotFound($path);
            }
        }
        
        if (!is_writable($path)) {
            
            throw new Application\Exception\RuntimeDirNotWritable($path);
        }
        
        $path = realpath($path);
        
        if ($suffix = trim($suffix)) {
            
            if ($suffix && substr($suffix, 0, 1) != '/')
                $suffix = '/' . $suffix;
            
            $full_path = $path . $suffix;
            
            if (!file_exists($full_path) && $create_dir) {
                
                mkdir($full_path, 0775, TRUE);
            }
        } else {
            
            $full_path = $path;
        }
        
        return $full_path;
    
    }

    /**
     * @brief Return the requested path in the current application
     *
     * @detail This method allows access to the raw URL path part, relative to the current application request.
     *
     * @since 1.0.0
     */
    static public function filePath($path = NULL, $file = NULL, $force_realpath = TRUE) {

        if (strlen($path) > 0)
            $path = '/' . trim($path, '/');
        
        if ($file)
            $path .= '/' . $file;
        
        $path = APPLICATION_PATH . ($path ? $path : NULL);
        
        $real = realpath($path);
        
        if ($force_realpath === FALSE && $real == FALSE)
            return $path;
        
        return $real;
    
    }

    /**
     * @brief Get the currently requested controller name
     *
     * @return string The current controller name
     */
    public function getRequestedController() {

        return $this->request->getControllerName();
    
    }

    /**
     * @brief Get the real path to the application on the local filesystem resolving links
     *
     * @since 1.0.0
     *       
     * @return string The resolved application path
     */
    public function getApplicationPath($suffix = '') {

        return realpath(APPLICATION_PATH . '/' . $suffix);
    
    }

    /**
     * @brief Get the base path
     *
     * @detail The base path is the root your application which contains the application, library and public
     * directories
     *
     * @since 1.0.0
     *       
     * @return string The resolved base path
     */
    public function getBasePath($suffix = '') {

        return realpath(APPLICATION_PATH . '/../' . $suffix);
    
    }

    /**
     * @brief Initialise the application ready for execution
     *
     * @detail Bootstrap is the first step in running an application. It will run some checks to make sure
     * sure the server has any required modules loaded as requested by the application (via the config). It
     * will then execute the application bootstrap.php script within the context of the application. Once
     * that step succeeds the requested (or the default) controller will be loaded and initialised so that
     * it is ready for execution by the application.
     *
     * @since 1.0.0
     *       
     * @return \Hazaar\Application Returns a reference to itself to allow chaining
     *        
     *         @exception Application\Exception\MissingModule If any required modules are missing
     *        
     *         @exception Application\Exception\NoRoute If the requested controller is could not be found and/or loaded
     *        
     */
    public function bootstrap($simple_mode = FALSE) {

        if ($this->timer) {
            
            $this->timer->start('pre_boot', HAZAAR_EXEC_START);
            
            $this->timer->stop('pre_boot');
            
            $this->timer->start('boot');
        }
        
        if ($this->getRequestedController() !== 'hazaar') {
            
            /*
             * Check that all required modules are loaded
             */
            if (!isset($this->config->module['require']))
                $this->config->module->require = array();
            
            if (count($missing = array_diff($this->config->module['require']->toArray(), get_loaded_extensions())) > 0) {
                
                throw new Application\Exception\ModuleMissing($missing);
            }
            
            /*
             * Check for an application bootstrap file and execute it
             */
            $bootstrap = APPLICATION_PATH . '/bootstrap.php';
            
            if (file_exists($bootstrap)) {
                
                $this->bootstrap = include ($bootstrap);
                
                if ($this->bootstrap === FALSE)
                    throw new \Exception('The application failed to start!');
            }
        }
        
        if ($simple_mode === FALSE) {
            
            if (!$this->request->processRoute()) {
                
                throw new Application\Exception\RouteFailed();
            }
            
            /*
             * Load the controller and check it was successful
             */
            $this->controller = $this->loader->loadController($this->request->getControllerName());
            
            if (!($this->controller instanceof Controller)) {
                
                throw new Application\Exception\RouteNotFound($this->request->getControllerName());
            }
            
            $this->controller->setRequest($this->request);
            
            /*
             * Initialise the controller with the current request
             */
            $this->controller->__initialize($this->request);
        }
        
        if ($this->timer)
            $this->timer->stop('boot');
        
        return $this;
    
    }

    /**
     * @brief Executes the application
     *
     * @detail Once the application has been initialised and a controller loaded, it can be executed via
     * the run() method. This will execute the loaded controller and check that it returns a
     * valid [[Hazaar\Controller\Response]] object. If a valid response is not returned an exception
     * will be raised.
     *
     * Once a valid response object is returned it will be used to write output back to the web server
     * to be returned to the user.
     *
     * This method also has protection against error loops. If an exception is thrown while processing
     * a [[Hazaar\Controller\Error]] controller object then a new exception will be thrown outside the
     * application context and will display basic fall-back error output.
     *
     * @since 1.0.0
     *       
     *        @exception Application\Exception\InvalidResponse If the controller returns an invalid response object
     *       
     *        @exception Application\Exception\ErrorLoop If an error was encountered while processing a
     *        [[Hazaar\Controller\Error]] object
     *       
     */
    public function run(Controller $controller = NULL) {

        if ($this->timer)
            $this->timer->start('exec');
        
        if (!$controller)
            $controller = $this->controller;
        
        try {
            
            /*
             * Execute the controllers run method.
             */
            $this->response = $controller->__run();
            
            /*
             * The run method should have returned a response object that we can output to the client
             */
            if (!($this->response instanceof Controller\Response)) {
                
                throw new Application\Exception\ResponseInvalid();
            }
            
            if ($this->config->app->has('tidy')) {
                
                $this->response->enableTidy($this->config->app['tidy']);
            }
            
            /*
             * If the controller has specifically requested a return status code, set it now.
             */
            if ($controller->statusCode)
                $this->response->setStatusCode($controller->statusCode);
                
                /*
             * Finally, write the response to the output buffer.
             */
            $this->response->__writeOutput();
            
            /*
             * Shutdown the controller
             */
            $controller->__shutdown();
        } catch(Exception $e) {
            
            /*
             * Here we check if the controller we tried to execute was already an error
             * If it is and we try and execute another error we could end up in an endless loop
             * so we throw a normal exception that will be grabbed by ErrorControl as an unhandled exception.
             */
            if ($controller instanceof Controller\Error) {
                
                die('FATAL: Error loop detected! Last error was: ' . $controller->getErrorMessage() . "\n\nTrace:\n\n<pre>" . print_r($controller->getTrace(), TRUE) . "</pre>");
            } else {
                
                throw $e;
            }
        }
        
        if ($this->timer) {
            
            $this->timer->start('post_exec');
            
            $this->timer->stop('exec');
        }
    
    }

    /**
     * @brief Execute code from standard input in the application context
     *
     * @detail This method is part of the background code execution scheduler, Bulletin. Code can be scheduled to
     * execute in the background at a later time. This code is stored in the schedulars memory and when
     * it's time to execute it, Bulletin will launch a new application context, bootstrap it, but
     * instead of calling the run() method like normal it will call this runStdin() method to execute the
     * stored code that was passed via stdin.
     *
     * @since 1.0.0
     */
    public function runStdin() {

        $code = 1;
        
        $defaults = array(
            'sys' => array(
                'id' => crc32(APPLICATION_PATH)
            ),
            'server' => array(
                'encoded' => FALSE
            )
        );
        
        $warlock = new \Hazaar\Application\Config('warlock.ini', NULL, $defaults);
        
        define('RESPONSE_PROTOCOL', 'runner');
        
        define('RESPONSE_ENCODED', $warlock->server->encoded);
        
        $protocol = new \Hazaar\Application\Protocol($warlock->sys->id, $warlock->server->encoded);
        
        $line = fgets(STDIN);
        
        $type = $protocol->decode($line, $payload);
        
        switch ($type) {
            
            case $protocol->getType('exec') :
                
                $params = (array_key_exists('params', $payload) ? $payload['params'] : array());
                
                eval('$_function = ' . $payload['function'] . ';');
                
                if (isset($_function) && $_function instanceof \Closure) {
                    
                    $result = call_user_func_array($_function, $params);
                    
                    if ($result === TRUE) {
                        
                        $code = 0;
                    } elseif (is_int($result)) {
                        
                        $code = $result;
                    } else {
                        
                        $code = 0;
                        
                        echo $protocol->encode('OK', $result);
                    }
                } else {
                    
                    $code = 2;
                }
                
                break;
            
            case $protocol->getType('SERVICE') :
                
                if (!array_key_exists('name', $payload)) {
                    
                    $code = 3;
                    
                    break;
                }
                
                $params = (array_key_exists('params', $payload) ? $payload['params'] : array());
                
                $serviceClass = ucfirst($payload['name']) . 'Service';
                
                if (class_exists($serviceClass)) {
                    
                    $service = new $serviceClass($this, $protocol);
                    
                    $code = call_user_func_array(array(
                        $service,
                        'main'
                    ), $params);
                } else {
                    
                    $code = 2;
                }
                
                break;
        }
        
        exit($code);
    
    }

    /**
     * @brief Return the requested path in the current application
     *
     * @detail This method allows access to the raw URL path part, relative to the current application request.
     *
     * @since 1.0.0
     */
    static public function path($path = NULL) {

        $root = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
        
        return $root . ($path ? trim($path, '/') : NULL);
    
    }

    /**
     * @brief Generate a URL relative to the application
     *
     * @detail This is the base method for generating URLs in your application. URLs generated directly from here
     * are relative to the application base path. For URLs that are relative to the current controller see
     * Controller::url()
     *
     * Parameters are dynamic and depend on what you are trying to generate.
     *
     * For examples see: "Generating URLs":http://www.hazaarmvc.com/docs/the-basics/generating-urls
     *
     * @since 1.0.0
     *       
     */
    static public function url() {

        $url = new Application\Url();
        
        call_user_func_array(array(
            $url,
            '__construct'
        ), func_get_args());
        
        return $url;
    
    }

    /**
     * @brief Send an immediate redirect response to redirect the browser
     *
     * @detail It's quite common to redirect the user to an alternative URL. This may be to forward the request
     * to another website, forward them to an authentication page or even just remove processed request
     * parameters from the URL to neaten the URL up.
     *
     * @since 1.0.0
     *       
     * @param $location string
     *            The URL you want to redirect to
     *            
     * @param $args array
     *            An optional array of parameters to tack onto the URL
     *            
     * @param $save_url boolean
     *            Optionally save the URL so we can redirect back. See: Application::redirectBack()
     */
    public function redirect($location, $args = array(), $save_url = TRUE) {

        if (!$args)
            $args = array();
        
        $url = $location . ((count($args) > 0) ? '?' . http_build_query($args) : NULL);
        
        $headers = apache_request_headers();
        
        if (array_key_exists('X-Requested-With', $headers) && $headers['X-Requested-With'] == 'XMLHttpRequest') {
            
            echo "<script>document.location = '$url';</script>";
        } else {
            
            $sess = new \Hazaar\Session();
            
            if ($sess->has('REDIRECT') && $sess['REDIRECT'] == $location)
                unset($sess['REDIRECT']);
            
            if ($save_url) {
                
                $sess['REDIRECT'] = array(
                    'URI' => $_SERVER['REQUEST_URI'],
                    'METHOD' => $_SERVER['REQUEST_METHOD']
                );
                
                if ($_SERVER['REQUEST_METHOD'] == 'POST')
                    $sess['REDIRECT']['POST'] = $_POST;
            }
            
            header('Location: ' . $url);
        }
        
        exit();
    
    }

    /**
     * @brief Redirect back to a URL saved during redirection
     *
     * @detail This mechanism is used with the $save_url parameter of Application::redirect() so save the current
     * URL into the session so that once we're done processing the request somewhere else we can come back
     * to where we were. This is useful for when a user requests a page but isn't authenticated, we can
     * redirect them to a login page and then that page can call this redirectBack() method to redirect the
     * user back to the page they were originally looking for.
     *
     * @since 1.0.0
     */
    public function redirectBack() {

        $sess = new \Hazaar\Session();
        
        if ($sess->has('REDIRECT')) {
            
            if ($uri = trim($sess['REDIRECT']['URI'])) {
                
                if ($sess['REDIRECT']['METHOD'] == 'POST') {
                    
                    if (substr($uri, -1, 1) !== '?')
                        $uri .= '?';
                    else
                        $uri .= '&';
                    
                    $uri .= http_build_query($sess['REDIRECT']['POST']);
                }
                
                unset($sess['REDIRECT']);
                
                header('Location: ' . $uri);
                
                exit();
            }
        }
        
        return FALSE;
    
    }

    public function version() {

        return HAZAAR_VERSION;
    
    }

}