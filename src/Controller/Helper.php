<?php
/**
 * @file        Hazaar/Controller/Helper.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
namespace Hazaar\Controller;

abstract class Helper {

    protected $controller;

    protected $args;
    
    final function __construct(\Hazaar\Controller $controller = NULL, $args = []) {

        $this->controller = $controller;
        
        $this->args = $args;

        $this->import($args);

    }

    public function getName() {

        $class = get_class($this);

        return substr($class, strrpos($class, '\\') + 1);

    }

    public function requires($helper, $args = []) {

        if(!$this->controller || $this->controller->hasHelper($helper)) 
            return false;

        $this->view->addHelper($helper, $args);

        return true;
        
    }

    //Placeholder functions
    public function import($args = []) {

        //Do nothing by default.

    }

}
