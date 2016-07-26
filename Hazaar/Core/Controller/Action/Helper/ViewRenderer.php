<?php

namespace Hazaar\Controller\Action\Helper;

class ViewRenderer extends \Hazaar\Controller\Action\Helper {

    private $view            = array();

    private $_data           = array();

    private $callbacks       = array();

    private $controller;

    private $_requires       = array();

    private $_requires_param = array();

    function init($controller = NULL) {

        if(! ($controller instanceof \Hazaar\Controller\Action)) {

            throw new Exception\InvalidActionController(get_class($controller));

        }

        //Store the controller
        $this->controller = $controller;

        /*
         * This helper has a method available to the controller so we register it
         */
        $controller->__registerMethod('view', array(
            $this,
            'view'
        ));

        $controller->__registerMethod('layout', array(
            $this,
            'layout'
        ));

        $controller->__registerMethod('setNoLayout', array(
            $this,
            'setNoLayout'
        ));

        $controller->__registerMethod('post', array(
            $this,
            'post'
        ));

    }

    public function addHelper($helper, $args = array()) {

        $this->view->addHelper($helper, $args);

    }

    public function getHelpers() {

        return $this->view->getHelpers();

    }

    public function __set($key, $value) {

        $this->_data[$key] = $value;

    }

    public function & __get($key) {

        if($this->view->hasHelper($key))
            return $this->view->getHelper($key);

        return $this->_data[$key];

    }

    /**
     * Sets the data available on any defined views.
     * 
     * @param array $array 
     */
    public function populate(array $array) {

        $this->_data = $array;

    }

    /**
     * Extends the data available on any defined views.
     * 
     * @param array $array 
     */
    public function extend(array $array) {

        $this->_data = array_merge($this->_data, $array);

    }

    /*
     * Use a layout view
     */

    public function layout($view) {

        if($this->view instanceof \Hazaar\View\Layout) {

            $this->view->load($view);

        } else {

            if(! $view instanceof \Hazaar\View\Layout) {

                $view = new \Hazaar\View\Layout($view);

            }

            $this->view = $view;

        }

    }

    public function setNoLayout() {

        $this->view = NULL;

    }

    /*
     * Set the current view, or add a view to a Hazaar_View_Layout
     */
    public function view($view) {

        if(! $view instanceof \Hazaar\View)
            $view = new \Hazaar\View($view, ($this->view == NULL));

        $view->registerMethodHandler($this->controller);

        if($this->view instanceof \Hazaar\View\Layout)
            $this->view->add($view);

        else
            $this->view = $view;

    }

    public function post($item) {

        if($this->view)
            $this->view->addPost($item);

    }

    /*
     * Helper execution call.  This renders the layout file.
     */
    public function __exec($controller, $response) {

        $content = $this->render($this->view, $controller);

        if(! $content) {

            throw new Exception\NoContent(get_class($this->view));

        }

        $response->addContent($content);

    }

    /*
     * Render the view/layout.
     *
     * - Supports layout views and renders all views inside the layout.
     * - Renders to the output buffer, then grabs the buffer and returns it.
     */
    public function render($view = NULL, $controller = NULL) {

        $output = 'no output';

        if($view instanceof \Hazaar\View)
            $output = $this->renderView($view, $controller);

        return $output;

    }

    private function renderView($view, $controller = NULL) {

        $view->registerMethodHandler($controller);

        $view->populate($this->_data);

        $view->initHelpers();

        if(is_array($this->_requires)) {

            $view->setRequiresParam($this->_requires_param);

            foreach($this->_requires as $req)
                $view->requires($req);

        }

        return $view->render();

    }

    public function setRequiresParam($array) {

        $this->_requires_param = array_merge($this->_requires_param, $array);

    }

    public function requires($script) {

        if(! method_exists($this->view, 'requires')) {

            throw new \Exception('The current view does not support script imports');

        }

        $this->_requires[] = $script;

    }

    public function link($href, $rel = NULL) {

        if(! method_exists($this->view, 'link')) {

            throw new \Exception('The current view does not support HTML links');

        }

        return $this->view->link($href, $rel);

    }

    public function script($code) {

        if(! method_exists($this->view, 'script')) {

            throw new \Exception('The current view does not support JavaScript code');

        }

        return $this->view->script($code);

    }

}