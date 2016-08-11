<?php

namespace Hazaar\Xml\Rpc\Exception;

class InvalidRequest extends \Hazaar\Exception {

    function __construct($method) {

        parent::__construct("Method '$method' is not a registered method.");

    }

}
