<?php

namespace Hazaar\Cache\Backend\Exception;

class NoMemcached extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('The memcached extension for PHP5 is required to be able to use the memcached cache backend.');

    }

}
