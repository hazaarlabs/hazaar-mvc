<?php
/**
 * @file        Hazaar/View/Helper.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View;

abstract class Helper implements Helper\_Interface {

    protected $view;

    protected $args;

    function __construct($view = NULL, $args = array()) {

        $this->view = $view;

        $this->args = $args;

        $this->import();

    }

    public function import() {

    }

    public function initialise($args = NULL) {

        if(! $args)
            $args = $this->args;

        if(method_exists($this, 'init'))
            $this->init($this->view, $args);

    }

    public function extendArgs($args) {

        if(! is_array($args))
            return;

        $this->args = array_merge($this->args, $args);

    }

    public function set($arg, $value) {

        $this->args[$arg] = $value;

    }

    public function get($arg) {

        if(array_key_exists($arg, $this->args))
            return $this->args[$arg];

        return NULL;

    }

    public function getName() {

        $class = get_class($this);

        return substr($class, strrpos($class, '\\') + 1);

    }

    public function init($view, $args = array()) {

    }

    public function requires($helper, $args = array()) {

        if($this->view && ! $this->view->hasHelper($helper)) {

            $this->view->addHelper($helper, $args);

        }

    }

    public function __get($method) {

        if($this->view)
            return $this->view->__get($method);

        return NULL;

    }

}