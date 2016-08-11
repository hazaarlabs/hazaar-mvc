<?php
/**
 * @file        Hazaar/Application/Router.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Application;

class Router {

    private $file;

    private $route;

    function __construct($route_file) {

        if(! file_exists($route_file)) {

            throw new \Exception('Routing file does not exist!');

        }

        $this->file = $route_file;

    }

    public function exec($current = NULL) {

        $this->route = $current;

        include($this->file);

        return (($this->route == $current) ? TRUE : $this->route);

    }

    private function get() {

        return $this->route;

    }

    private function set($route) {

        $this->route = $route;

    }

}
