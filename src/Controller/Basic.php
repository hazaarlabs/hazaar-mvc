<?php
/**
 * @file        Controller/Basic.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Basic controller class
 *
 * @detail      This controller is a basic controller for directly handling requests.  Developers can extend this class
 *              to create their own flexible controllers for use in modern AJAX enabled websites that don't require
 *              HTML views.
 *
 *              How it works is a request is passed to the controller and the controller is responsible for processing
 *              it, creating a new response object and return that object back to the application for processing.
 *
 *              This controller type is typically used for handling AJAX requests as responses to these requests do not
 *              require rendering any views.  This allows AJAX requests to be processed quickly without the overhead of
 *              rendering a view that will never be displayed.
 */
abstract class Basic extends \Hazaar\Controller {

    protected $action        = 'index';

    protected $actionArgs    = array();

    protected $cachedActions = array();

    public function cacheAction($action, $timeout = 60) {

        /*
         * To cache an action:
         * * Caching library has to be installed
         * * The method being cached must exist on the controller
         * * The method must not already be set to cache
         */
        if(!class_exists('Hazaar\Cache')
            || !method_exists($this, $action)
            || array_key_exists($action, $this->cachedActions))
            return false;

        $this->cachedActions[$action] = $timeout;

        return true;

    }

    public function getAction() {

        return $this->action;

    }

    public function getActionArgs() {

        return $this->actionArgs;

    }

    public function __initialize(\Hazaar\Application\Request $request) {

        if(! ($this->action = $request->getActionName()))
            $this->action = 'index';

        if(method_exists($this, 'init'))
            $this->init($request);

    }

    public function __run() {

        $args = array();

        if($path = $this->application->request->getPath())
            $args = explode('/', $path);

        if(! method_exists($this, $this->action)) {

            if(method_exists($this, '__default')) {

                array_unshift($args, $this->action);

                array_unshift($args, $this->application->getRequestedController());

                $this->action = '__default';

            } else {

                throw new Exception\ActionNotFound(get_class($this), $this->action);

            }

        }

        $method = new \ReflectionMethod($this, $this->action);

        if(! $method->isPublic())
            throw new Exception\ActionNotPublic(get_class($this), $this->action);

        $response = null;

        /**
         * Check the cached actions to see if this requested should use a cached version
         */
        if(array_key_exists($this->action, $this->cachedActions)) {

            $cache = new \Hazaar\Cache();

            $key = $this->name . '::' . $this->action;

            $response = $cache->get($key, $args);

        }

        if(!$response instanceof Response){

            $response = call_user_func_array(array($this, $this->action), $args);

            if(is_array($response)) {

                $response = new Response\Json($response);

            } elseif(! is_object($response)) {

                $response = new Response\Text($response);

            }

            if(isset($cache) && isset($key))
                $cache->set($key, $response, $this->cachedActions[$this->action]);

        }

        if($response instanceof Response)
            $response->setController($this);

        return $response;

    }

    /**
     * Test if a controller and action is active.
     *
     * @param mixed $controller
     * @param mixed $action
     * @return boolean
     */
    public function active($controller = NULL, $action = NULL) {

        if($controller instanceof \Hazaar\Application\Url){

            $action = $controller->method;

            $controller = $controller->controller;

        }

        if(is_array($controller)) {

            $parts = $controller;

            if(count($parts) > 0)
                $controller = array_shift($parts);

            if(count($parts) > 0)
                $action = array_shift($parts);

        }

        if(! $controller)
            $controller = $this->getName();

        if(! $action)
            $action = 'index';

        $params_match = true;

        if(strpos($action, '/') > 0){

            $args = explode('/', $action);

            $action = array_shift($args);

            $params_match = (count(array_intersect_assoc($args, $this->actionArgs)) > 0);

        }

        return (strcasecmp($this->getName(), $controller) == 0 && strcasecmp($this->getAction(), $action) == 0 && $params_match);

    }

}
