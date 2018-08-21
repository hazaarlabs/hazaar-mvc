<?php

namespace Hazaar\Xml\Rpc\Exception;

class MethodNotFound extends \Hazaar\Exception {

    function __construct($method) {

        parent::__construct("Method '$method' is not a registered method.");

    }

}
