<?php

namespace Hazaar\Controller\Exception;

class MethodNotFound extends \Hazaar\Exception {

    function __construct($class, $method_name) {

        parent::__construct("Method not found while trying to execute $class::$method_name()");

    }

}
