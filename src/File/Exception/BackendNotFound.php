<?php

namespace Hazaar\File\Exception;

class BackendNotFound extends \Hazaar\Exception {

    function __construct($backend) {

        parent::__construct("Unknown filesystem backend : '$backend'");

    }

}
