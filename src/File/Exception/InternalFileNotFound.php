<?php

namespace Hazaar\File\Exception;

class InternalFileNotFound extends \Hazaar\Exception {

    function __construct($file) {

        parent::__construct("Internal file not found while requesting file '$file'", 404);

    }

}

