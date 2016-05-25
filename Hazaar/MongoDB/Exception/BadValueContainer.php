<?php

namespace Hazaar\MongoDB\Exception;

class BadValueContainer extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('An iteratorable class was supplied but it is not an Array and does not have a toArray method to convert it to an array.  Saving this to a MongoDB database will fail.');

    }

}

