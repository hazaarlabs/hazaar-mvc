<?php

namespace Hazaar\Cache\Exception;

class InvalidBackend extends \Hazaar\Exception {

    function __construct($class) {

        parent::__construct("Object of class '$class' is not a valid cache backend!");

    }

}
