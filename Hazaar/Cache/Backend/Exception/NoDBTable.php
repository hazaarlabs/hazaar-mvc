<?php

namespace Hazaar\Cache\Backend\Exception;

class NoDBTable extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('A cache table is required when using the Database cache backend');

    }

}
