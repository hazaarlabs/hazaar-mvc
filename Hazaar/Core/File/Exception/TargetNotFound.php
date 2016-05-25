<?php

namespace Hazaar\File\Exception;

class TargetNotFound extends \Hazaar\Exception {

    function __construct($target, $source) {

        parent::__construct("Destination '$target' does not exist while trying to copy '$source'.");

    }

}
