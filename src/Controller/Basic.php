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

    protected $cache         = null;

    protected $cache_key     = null;

    public function cacheAction($action, $timeout = 60, $public = false) {

        /*
         * To cache an action the caching library has to be installed
         */
        if(!class_exists('Hazaar\Cache'))
            throw new \Exception('The Hazaar\Cache class is not available.  Please make sure the hazaar-cache library is correctly installed', 401);

        $this->cache = new \Hazaar\Cache();

        $this->cachedActions[$action] = array('timeout' => $timeout, 'public' => $public);

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

    /**
     * Run an action method on a controller
     *
     * This is the main controller action decision code and is where the controller will decide what to
     * actually execute and whether to cache the response on not.
     *
     * @param mixed $action The name of the action to run
     *
     * @throws Exception\ActionNotFound
     * @throws Exception\ActionNotPublic
     *
     * @return mixed
     */
    protected function __runAction($action_name) {

        $args = array();

        if($path = $this->application->request->getPath())
            $args = explode('/', $path);

        $action = $action_name;

        if(! method_exists($this, $action)) {

            if(method_exists($this, '__default')) {

                array_unshift($args, $action);

                array_unshift($args, $this->application->getRequestedController());

                $action = '__default';

            } else {

                throw new Exception\ActionNotFound(get_class($this), $this->action);

            }

        }

        /**
         * Check the cached actions to see if this requested should use a cached version
         */
        if($this->cache && array_key_exists($action_name, $this->cachedActions)) {

            $this->cache_key = $this->name . '::' . $action_name . '(' . serialize($this->actionArgs) . ')';

            if($this->cachedActions[$action_name]['public'] !== true && $sid = session_id())
                $this->cache_key .= '::' . $sid;

            if($response = $this->cache->get($this->cache_key, $args))
                return $response;

        }

        $method = new \ReflectionMethod($this, $action);

        if(! $method->isPublic())
            throw new Exception\ActionNotPublic(get_class($this), $this->action);

        $response = $method->invokeArgs($this, $args);

        return $response;

    }

    public function __run(){

        $response = $this->__runAction($this->action);

        if(!$response instanceof Response){

            $response = (is_array($response) || is_object($response)) ? new Response\Json($response) : new Response\Text($response);

            if($this->cache_key !== null)
                $this->cache->set($this->cache_key, $response, $this->cachedActions[$this->action]['timeout']);

        }

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
