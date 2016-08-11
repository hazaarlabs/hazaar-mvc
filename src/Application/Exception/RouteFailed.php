<?php

namespace Hazaar\Application\Exception;

class RouteFailed extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Failed to process routing information!');

    }

}
