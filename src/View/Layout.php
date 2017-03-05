<?php
/**
 * @file        Hazaar/View/Layout.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View;

class Layout extends \Hazaar\View implements \ArrayAccess, \Iterator {

    private $_content = '';

    private $_views   = array();

    public function __construct($view = NULL, $init_default_helpers = true, $use_app_config = true) {

        if(! $view)
            $view = 'application';

        parent::__construct($view, $init_default_helpers, $use_app_config);

    }

    public function setContent($content) {

        $this->_content = $content;

    }

    /**
     * Render the layout after importing any required files from child views.
     */
    public function render() {

        /**
         * Append any requires to the view layout so that they are imported correctly with the View::import() call.
         */
        if(is_array($this->_views)) {

            foreach($this->_views as $view) {

                foreach($view->_requires as $priority => $requires)
                    $this->_requires[$priority] = array_merge_recursive($this->_requires[$priority], $requires);

            }

        }

        return parent::render();

    }

    public function layout() {

        $output = $this->_content;

        foreach($this->_views as $view) {

            foreach($this->_helpers as $obj)
                $view->addHelper($obj);

            $view->registerMethodHandler($this->_methodHandler);

            $view->extend($this->_data);

            $output .= $view->render();

        }

        return $output;

    }

    /**
     * Add a view to the layout
     *
     * This method will add a view based on the supplied argument.  If the argument is a string a new Hazaar\View object
     * is created using the view file named in the argument.  Alterntively, the argument can be a Hazaar\View object
     * which will simply then be added to the layout.
     *
     * @param $view mixed A string naming the view to load, or an existing Hazaar_View object.
     *
     * @param $key string Optional key to store the view as.  Allows direct referencing later.
     *
     * @return \Hazaar\View
     */
    public function add($view, $key = NULL) {

        if(! $view instanceof \Hazaar\View)
            $view = new \Hazaar\View($view, FALSE);

        if($key)
            $this->_views[$key] = $view;

        else
            $this->_views[] = $view;

        return $view;

    }

    public function remove($key) {

        if(array_key_exists($key, $this->_views))
            unset($this->_views[$key]);

    }

    /*
     * Array Access
     */

    public function offsetExists($offset) {

        return array_key_exists($offset, $this->_views);

    }

    public function offsetGet($offset) {

        return $this->_views[$offset];

    }

    public function offsetSet($offset, $value) {

        $this->add($value, $offset);

    }

    public function offsetUnset($offset) {

        $this->remove($offset);

    }

    /*
     * Iterator
     */
    public function current() {

        return current($this->_views);

    }

    public function key() {

        return key($this->_views);

    }

    public function next() {

        return next($this->_views);

    }

    public function rewind() {

        return rewind($this->_views);

    }

    public function valid() {

        return current($this->_views);

    }

}