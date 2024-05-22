<?php

namespace Hazaar\DBI\DBD\Exception;

class NotConnected extends \Exception {

    function __construct() {

        parent::__construct('PDO is not available or not connected.');

    }

}
