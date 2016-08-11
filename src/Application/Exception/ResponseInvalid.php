<?php

namespace Hazaar\Application\Exception;

class ResponseInvalid extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Invalid controller response received.  Controllers should return an object type Hazaar\\Controller\\Response.');

    }

}
