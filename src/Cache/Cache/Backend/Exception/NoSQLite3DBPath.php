<?php

namespace Hazaar\Cache\Backend\Exception;

class NoSQLite3DBPath extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('A cache DB path is required when using the SQlite cache backend');

    }

}
