<?php

namespace Hazaar\File\Exception;

class InvalidBackend extends \Hazaar\Exception {

    function __construct($backend) {

        parent::__construct("Invalid filesystem backend : '$backend'");

    }

}
