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

define('HAZAAR_VERSION', '2.3');

defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(dirname($_SERVER['SCRIPT_FILENAME']) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'application'));

defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'development'));

putenv('HOME=' . APPLICATION_PATH);

/**
 * Change the current working directory to the application path so that all paths are relative to it.
 */
chdir(APPLICATION_PATH);

require_once ('ErrorControl.php');

require_once('HelperFunctions.php');

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

    private static $root;

    private $protocol;

    /**
     * @brief The main application constructor
     *
     * @detail The application is basically the center of the Hazaar MVC universe. Everything hangs off of it
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
     * @param string $env
     *            The application environment name. eg: 'development' or 'production'
     */
    function __construct($env) {

        Application::$instance = $this;

        $this->environment = $env;

        if(!defined('HAZAAR_INIT_START'))
            define('HAZAAR_INIT_START', microtime(TRUE));

        /*
         * Create a loader object and register it as the default autoloader
         */
        $this->loader = Loader::getInstance($this);

        $this->loader->register();

        /**
         * Set up some default config properties.
         */
        $defaults = array(
            'app' => array(
                'root' => dirname($_SERVER['SCRIPT_NAME']),
                'defaultController' => 'Index',
                'favicon' => 'favicon.png',
                'timezone' => 'UTC',
                'rewrite' => (php_sapi_name() !== 'cli-server'),
                'files' => array(
                    'bootstrap' => 'bootstrap.php',
                    'shutdown' => 'shutdown.php',
                    'route' => 'route.php',
                    'media' => 'media.php'
                )
            ),
            'paths' => array(
                'model' => 'models',
                'view' => 'views',
                'controller' => 'controllers',
                'service' => 'services'
            ),
            'view' => array(
                'prepare' => false
            )
        );

        /*
         * Load it with a config object. if the file doesn't exist
         * it will just be an empty object that will handle calls to
         * it silently.
         */
        $this->config = new Application\Config('application', $env, $defaults, FILE_PATH_CONFIG);

        Application\Url::$base = $this->config->app->get('base');

        Application\Url::$rewrite = $this->config->app->get('rewrite');

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

        /**
         * Check the load average and protect ifneeded
         */
        if($this->config->app->has('maxload') && function_exists('sys_getloadavg')) {

            $la = sys_getloadavg();

            if($la[0] > $this->config->app['maxload'])
                throw new Application\Exception\ServerBusy();

        }

        /*
         * Create a timer for performance measuring
         */
        if($this->config->app->has('timer') && $this->config->app['timer'] == TRUE) {

            $this->timer = new Timer();

            $this->timer->start('init', HAZAAR_INIT_START);

            $this->timer->stop('init');

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
     * @detail The destructor cleans up any application redirections. ifthe controller hasn't used it in this
     * run then it loses it. This prevents stale redirect URLs from accidentally being used.
     *
     * @since 1.0.0
     *
     */
    function __destruct() {

        if($this->config){

            $shutdown = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($this->config->app->files, 'shutdown', 'shutdown.php');

            if(file_exists($shutdown))
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

    static public function setRoot($value){

        Application::$root = rtrim($value, '/') . '/';

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

        $path = APPLICATION_PATH . DIRECTORY_SEPARATOR . ($this->config->app->has('runtimepath') ? $this->config->app->runtimepath : '.runtime');

        if(!file_exists($path)) {

            $parent = dirname($path);

            if(!is_writable($parent))
                throw new Application\Exception\RuntimeDirUncreatable($path);

            // Try and create the directory automatically
            try {

                mkdir($path, 0775);

            }
            catch(\Exception $e) {

                throw new Application\Exception\RuntimeDirNotFound($path);

            }

        }

        if(!is_writable($path))
            throw new Application\Exception\RuntimeDirNotWritable($path);

        $path = realpath($path);

        if($suffix = trim($suffix)) {

            if($suffix && substr($suffix, 0, 1) != DIRECTORY_SEPARATOR)
                $suffix = DIRECTORY_SEPARATOR . $suffix;

            $full_path = $path . $suffix;

            if(!file_exists($full_path) && $create_dir)
                mkdir($full_path, 0775, TRUE);

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

        if(strlen($path) > 0)
            $path = '/' . trim($path, '/');

        if($file)
            $path .= '/' . $file;

        $path = APPLICATION_PATH . ($path ? $path : NULL);

        $real = realpath($path);

        if($force_realpath === FALSE && $real == FALSE)
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
     */
    public function bootstrap($simple_mode = FALSE) {

        if($this->timer) {

            $this->timer->start('pre_boot', HAZAAR_EXEC_START);

            $this->timer->stop('pre_boot');

            $this->timer->start('boot');

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
            throw new \Exception("Unable to set locale to $locale.  Make sure the $locale locale is enabled on your system.");

        if($this->config->app->has('timezone'))
            $tz = $this->config->app->timezone;
        else
            $tz = 'UTC';

        if(!date_default_timezone_set($tz))
            throw new Application\Exception\BadTimezone($tz);

        if($this->getRequestedController() !== 'hazaar') {

            /*
             * Check that all required modules are loaded
             */
            if(!isset($this->config->module['require']))
                $this->config->module->require = array();

            if(count($missing = array_diff($this->config->module['require']->toArray(), get_loaded_extensions())) > 0)
                throw new Application\Exception\ModuleMissing($missing);

            /*
             * Check for an application bootstrap file and execute it
             */
            $bootstrap = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($this->config->app->files, 'bootstrap', 'bootstrap.php');

            if(file_exists($bootstrap)) {

                $this->bootstrap = include($bootstrap);

                if($this->bootstrap === FALSE)
                    throw new \Exception('The application failed to start!');

            }

        }

        if($simple_mode === FALSE) {

            if(!$this->request->processRoute())
                throw new Application\Exception\RouteFailed();

            /*
             * Load the controller and check it was successful
             */
            $this->controller = $this->loader->loadController($this->request->getControllerName());

            if(!($this->controller instanceof Controller))
                throw new Application\Exception\RouteNotFound($this->request->getControllerName());

            $this->controller->setRequest($this->request);

            /*
             * Initialise the controller with the current request
             */
            $this->controller->__initialize($this->request);

        }

        if($this->timer)
            $this->timer->stop('boot');

        return $this;

    }

    /**
     * @brief Executes the application
     *
     * @detail Once the application has been initialised and a controller loaded, it can be executed via
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

        if($this->timer)
            $this->timer->start('exec');

        if(!$controller)
            $controller = $this->controller;

        try {

            /*
             * Execute the controllers run method.
             */
            $this->response = $controller->__run();

            /*
             * The run method should have returned a response object that we can output to the client
             */
            if(!($this->response instanceof Controller\Response))
                throw new Application\Exception\ResponseInvalid();

            /*
             * If the controller has specifically requested a return status code, set it now.
             */
            if($controller->statusCode)
                $this->response->setStatusCode($controller->statusCode);

            $this->response->setCompression($this->config->app->get('compress', false));

            /*
             * Finally, write the response to the output buffer.
             */
            $this->response->__writeOutput();

            /*
             * Shutdown the controller
             */
            $controller->__shutdown();

        }
        catch(Controller\Exception\HeadersSent $e) {

            die('HEADERS SENT');

        }
        catch(Exception $e) {

            /*
             * Here we check if the controller we tried to execute was already an error
             * if it is and we try and execute another error we could end up in an endless loop
             * so we throw a normal exception that will be grabbed by ErrorControl as an unhandled exception.
             */
            if($controller instanceof Controller\Error)
                die('FATAL: Error loop detected! Last error was: '
                . $controller->getErrorMessage() . "\n\nTrace:\n\n<pre>"
                . print_r($controller->getTrace(), TRUE) . "</pre>");

            else
                throw $e;

        }

        if($this->timer) {

            $this->timer->start('post_exec');

            $this->timer->stop('exec');

        }

    }

    /**
     * @brief Execute code from standard input in the application context
     *
     * @detail This method is will accept Hazaar Protocol commands from STDIN and execute them.
     *
     * Exit codes:
     *
     * * 1 - Bad Payload - The execution payload could not be decoded.
     * * 2 - Unknown Payload Type - The payload execution type is unknown.
     * * 3 - Service Class Not Found - The service could not start because the service class could not be found.
     * * 4 - Unable to open control channel - The application was unable to open a control channel back to the execution server.
     *
     * @since 1.0.0
     */
    public function runStdin() {

        if(!class_exists('\Hazaar\Warlock\Config'))
            throw new \Exception('Could not find default warlock config.  How is this even working!!?');

        $defaults = \Hazaar\Warlock\Config::$default_config;

        $defaults['sys']['id'] = crc32(APPLICATION_PATH);

        $warlock = new \Hazaar\Application\Config('warlock', APPLICATION_ENV, $defaults);

        define('RESPONSE_ENCODED', $warlock->server->encoded);

        $protocol = new \Hazaar\Application\Protocol($warlock->sys->id, $warlock->server->encoded);

        //Execution should wait here until we get a command
        $line = stream_get_contents(STDIN);

        $code = 1;

        if($type = $protocol->decode($line, $payload)){

            switch ($type) {

                case 'EXEC' :

                    $container = new \Hazaar\Warlock\Container($this, $protocol);

                    if($container->connect($payload['application_name'], '127.0.0.1', $payload['server_port'], $payload['job_id'], $payload['access_key'])){

                        $code = $container->exec($payload['function'], ake($payload, 'params'));

                    }else{

                        $code = 4;

                    }

                    break;

                case 'SERVICE' :

                    if(!array_key_exists('name', $payload)) {

                        $code = 3;

                        break;

                    }

                    $serviceClass = ucfirst($payload['name']) . 'Service';

                    if(class_exists($serviceClass)) {

                        if($config = ake($payload, 'config')){

                            $this->config->extend($config);

                            $this->config->app['test'] = '1234';

                        }

                        $service = new $serviceClass($this, $protocol);

                        if($service->connect($payload['application_name'], '127.0.0.1', $payload['server_port'], $payload['job_id'], $payload['access_key'])){

                            $code = call_user_func(array($service, 'main'));

                        }else{

                            $code = 4;

                        }

                    } else {

                        $code = 3;

                    }

                    break;

                default:

                    $code = 2;

                    break;

            }

        }

        exit($code);

    }

    private function trigger($event, $data){

        if(!$this->protocol instanceof \Hazaar\Application\Protocol)
            return false;

        $packet = array(
            'id' => $event
        );

        if($data)
            $packet['data'] = $data;

        echo $this->protocol->encode('trigger', $packet) . "\n";

        flush();

        return true;

    }

    /**
     * @brief Return the requested path in the current application
     *
     * @detail This method allows access to the raw URL path part, relative to the current application request.
     *
     * @since 1.0.0
     */
    static public function path($path = NULL) {

        return Application::$root . ($path ? trim($path, '/') : NULL);

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
    public function url() {

        $url = new Application\Url();

        call_user_func_array(array($url, '__construct'), func_get_args());

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

        if(!$args)
            $args = array();

        $url = $location . ((count($args) > 0) ? '?' . http_build_query($args) : NULL);

        $headers = apache_request_headers();

        if(array_key_exists('X-Requested-With', $headers) && $headers['X-Requested-With'] == 'XMLHttpRequest') {

            echo "<script>document.location = '$url';</script>";

        } elseif(class_exists('Hazaar\Session')) {

            $sess = new \Hazaar\Session();

            if($sess->has('REDIRECT') && $sess['REDIRECT'] == $location)
                unset($sess['REDIRECT']);

            if($save_url) {

                $sess['REDIRECT'] = array(
                    'URI' => $_SERVER['REQUEST_URI'],
                    'METHOD' => $_SERVER['REQUEST_METHOD']
                );

                if($_SERVER['REQUEST_METHOD'] == 'POST')
                    $sess['REDIRECT']['POST'] = $_POST;

            }

        }

        header('Location: ' . $url);

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

        if($sess->has('REDIRECT')) {

            if($uri = trim(ake($sess['REDIRECT'], 'URI'))) {

                if(ake($sess['REDIRECT'], 'METHOD') == 'POST') {

                    if(substr($uri, -1, 1) !== '?')
                        $uri .= '?';
                    else
                        $uri .= '&';

                    $uri .= http_build_query(ake($sess['REDIRECT'], 'POST'));

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
