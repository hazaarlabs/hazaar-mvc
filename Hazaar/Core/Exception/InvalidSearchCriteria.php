<?php

namespace Hazaar\Exception;

class InvalidSearchCriteria extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Invalid search criteria supplied!');

    }

}
