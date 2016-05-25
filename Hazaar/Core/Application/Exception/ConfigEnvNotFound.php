<?php

namespace Hazaar\Application\Exception;

class RouteFailed extends \Hazaar\Exception {

    function __construct($env) {

        parent::__construct("The required configuration environment '$env' does not exist.");

    }

}
