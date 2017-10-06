<?php
/**
 * @file        Controller/Action.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Abstract controller action class
 *
 * @detail      This controller handles actions and responses using views
 */
abstract class Action extends \Hazaar\Controller\Basic {

    public    $view;

    public    $_helper;

    protected $methods       = array();

    private   $stream        = FALSE;

    public function __construct($name, $application, $use_app_config = true) {

        parent::__construct($name, $application, $use_app_config);

        $this->_helper = new Action\HelperBroker($this);

        if(! $this->view = $this->_helper->addHelper('ViewRenderer'))
            throw new Exception\NoDefaultRenderer();

        if($use_app_config && $this->application->config->app->has('layout')) {

            $this->_helper->ViewRenderer->layout($this->application->config->app['layout'], true);

            if($this->application->config->app->has('favicon'))
                $this->_helper->ViewRenderer->link($this->application->config->app['favicon'], 'shortcut icon');

        }

    }

    public function __registerMethod($name, $callback) {

        if(array_key_exists($name, $this->methods))
            throw new Exception\MethodExists($name);

        $this->methods[$name] = $callback;

        return TRUE;

    }

    public function __call($method, $args) {

        if(array_key_exists($method, $this->methods))
            return call_user_func_array($this->methods[$method], $args);

        throw new Exception\MethodNotFound(get_class($this), $method);

    }

    public function __get($plugin) {

        throw new \Exception('Controller plugins not supported yet.  Called: ' . $plugin);

        if(array_key_exists($plugin, $this->plugins))
            return $this->plugins[$plugin];

        return NULL;

    }

    public function __initialize($request) {

        if(! ($this->action = $request->getActionName()))
            $this->action = 'index';

        if(method_exists($this, 'init')) {

            $ret = $this->init($request);

            if($ret === FALSE)
                throw new \Exception('Failed to initialize action controller! ' . get_class($this) . '::init() returned false!');

        }

        if(($path = $this->application->request->getPath()) !== '')
            $this->actionArgs = explode('/', $path);

    }

    public function __run() {

        $action = $this->action;

        $args = $this->actionArgs;

        if(! method_exists($this, $action)) {

            if(method_exists($this, '__default')) {

                array_unshift($args, $action);

                array_unshift($args, $this->application->getRequestedController());

                $action = '__default';

            } else {

                throw new Exception\ActionNotFound(get_class($this), $action);

            }

        }

        $method = new \ReflectionMethod($this, $action);

        if(! $method->isPublic())
            throw new Exception\ActionNotPublic(get_class($this), $action);

        $response = null;

        /**
         * Check the cached actions to see if this requested should use a cached version
         */
        if(array_key_exists($action, $this->cachedActions)) {

            $cache = new \Hazaar\Cache();

            $key = $this->name . '::' . $action;

            $response = $cache->get($key, $args);

        }

        if(! $response instanceof Response) {

            /*
             * Execute the requested action
             */
            $response = call_user_func_array(array($this, $action), $args);

            if($this->stream)
                return new Response\Stream($response);

            if(! ($response instanceof Response)) {

                if(is_array($response)) {

                    $response = new Response\Json($response);

                } else {

                    $response = new Response\Html();

                    /*
                     * Execute the action helpers.  These are responsible for actually rendering any views.
                     */
                    $this->_helper->execAllHelpers($this, $response);

                    if($this->application->config->app->has('tidy'))
                        $response->enableTidy($this->application->config->app->get('tidy', false));

                }

            }

            if(isset($cache) && isset($key))
                $cache->set($key, $response, $this->cachedActions[$action]);

        }

        if($response instanceof Response)
            $response->setController($this);

        return $response;

    }

    public function stream($value) {

        if(! headers_sent()) {

            ob_end_flush();

            header('X-Accel-Buffering: no');

            header('Content-Type: application/octet-stream;charset=ISO-8859-1');

            flush();

            $this->stream = TRUE;

        }

        if(is_array($value))
            $value = json_encode($value);

        echo dechex(strlen($value)) . "\0" . $value;

        flush();

        return TRUE;

    }

}