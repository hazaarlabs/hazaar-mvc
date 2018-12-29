<?php

namespace Hazaar\Cache\Backend\Exception;

class NoDBConfig extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('The Database cache backend requires database configuration parameters.');

    }

}
