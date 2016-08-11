<?php

namespace Hazaar\MongoDB\Exception;

class BadDateValue extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Object not a MongoDate.  We need to figure out how to handle these types of stored dates.');

    }

}

