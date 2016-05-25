<?php

namespace Hazaar\Controller\Action;

abstract class Helper {

    private $controller;

    function __construct($controller) {

        $this->controller = $controller;

        if(method_exists($this, 'init')) {

            $this->init($this->controller);

        }

    }

    public function init() {

        //Do Nothing

    }

}
