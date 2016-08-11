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

    private $action   = 'index';

    private $javaMode = FALSE;

    public function __initialize($request) {

        if(! ($this->action = $request->getActionName())) {

            $this->action = 'index';

        }

        if(method_exists($this, 'init')) {

            $this->init($request);

        }

    }

    public function __run() {

        $args = array();

        if($path = $this->application->request->getPath()) {

            $args = explode('/', $path);

        }

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

        if(! $method->isPublic()) {

            throw new Exception\ActionNotPublic(get_class($this), $this->action);

        }

        $response = call_user_func_array(array(
            $this,
            $this->action
        ), $args);

        if($this->javaMode || is_array($response)) {

            $response = new Response\Json($response);

        } elseif(! is_object($response)) {

            $response = new Response\Text($response);

        }

        if($response instanceof Response) {

            $response->setController($this);

        }

        return $response;

    }

    protected function enableJavaMode() {

        $this->javaMode = TRUE;

    }

}

