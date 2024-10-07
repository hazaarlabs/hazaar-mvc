<?php

namespace Hazaar\Controller\Action\Helper;

class ViewRenderer extends \Hazaar\Controller\Action\Helper implements \ArrayAccess  {

    private $view            = [];

    private $_data           = [];

    private $callbacks       = [];

    private $controller;

    private $_requires       = [];

    private $_requires_param = [];

    private $_links          = [];

    private $_scripts        = [];

    function init($controller = NULL) {

        if(! ($controller instanceof \Hazaar\Controller\Action))
            throw new Exception\InvalidActionController(get_class($controller));

        //Store the controller
        $this->controller = $controller;

        /*
         * This helper has a method available to the controller so we register it
         */
        $controller->__registerMethod('hasView', [$this, 'hasView']);

        $controller->__registerMethod('view', [$this, 'view']);

        $controller->__registerMethod('layout', [$this, 'layout']);

        $controller->__registerMethod('setNoLayout', [$this, 'setNoLayout']);

        $controller->__registerMethod('post', [$this, 'post']);

    }

    public function addHelper($helper, $args = [], $alias = null) {

        if(!$this->view instanceof \Hazaar\View)
            return false;

        $this->view->addHelper($helper, $args, $alias);

        return true;
    }

    public function getHelpers() {

        if($this->view instanceof \Hazaar\View)
            return $this->view->getHelpers();

        return null;

    }

    public function removeHelper($name) {

        if($this->view instanceof \Hazaar\View)
            $this->view->removeHelper($name);

    }

    public function offsetExists($key) {

        return isset($this->_data[$key]);

    }

    public function offsetGet($key) {

        return $this->_data[$key];

    }

    public function offsetSet($key, $value) {

        $this->_data[$key] = $value;

    }

    public function offsetUnset($key) {

        unset($this->_data[$key]);

    }

    public function __set($key, $value) {

        $this->_data[$key] = $value;

    }

    public function & __get($key) {

        if($this->view instanceof \Hazaar\View && $this->view->hasHelper($key))
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

    public function hasView($view){

        return (\Hazaar\View::getViewPath($view, $name) !== null);

    }

    /*
     * Use a layout view
     */

    public function layout($view) {

        if($this->view instanceof \Hazaar\View\Layout) {

            $this->view->load($view);

        } else {

            if(! $view instanceof \Hazaar\View\Layout)
                $view = new \Hazaar\View\Layout($view);

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

        if(! $content)
            throw new Exception\NoContent(get_class($this->view));

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

        $view->extend($this->_data);

        $view->initHelpers();

        $view->runHelpers();

        if(is_array($this->_requires)) {

            $view->setRequiresParam($this->_requires_param);

            foreach($this->_requires as $req)
                $view->requires($req[0], $req[1], $req[2], $req[3]);

        }

        if(is_array($this->_links)){

            foreach($this->_links as $link)
                $view->link($link[0], $link[1], $link[2]);

        }

        if(is_array($this->_scripts)){

            foreach($this->_scripts as $script)
                $view->script($script);

        }

        return $view->render();

    }

    public function setRequiresParam($array) {

        $this->_requires_param = array_merge($this->_requires_param, $array);

    }

    public function requires($script, $charset = NULL, $cache_local = null, $defer = false) {

        if(! method_exists($this->view, 'requires'))
            throw new \Hazaar\Exception('The current view does not support script imports');

        $this->_requires[] = [$script, $charset, $cache_local, $defer];

    }

    public function link($href, $rel = NULL, $cache_local = null) {

        if(! method_exists($this->view, 'link'))
            throw new \Hazaar\Exception('The current view does not support HTML links');

        $this->_links[] = [$href, $rel, $cache_local];

    }

    public function script($code) {

        if(! method_exists($this->view, 'script'))
            throw new \Hazaar\Exception('The current view does not support JavaScript code');

        $this->_scripts[] = $code;

    }

}