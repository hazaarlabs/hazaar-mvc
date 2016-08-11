<?php

namespace Hazaar\Controller\Exception;

class MethodExists extends \Hazaar\Exception {

    function __construct($method_name) {

        parent::__construct("Error trying to register controller method '$method_name'.  A method with that name already exist.");

    }

}
