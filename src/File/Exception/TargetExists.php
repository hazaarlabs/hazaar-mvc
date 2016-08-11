<?php

namespace Hazaar\File\Exception;

class TargetExists extends \Hazaar\Exception {

    function __construct($target) {

        parent::__construct("Destination file already exists at '$target'");

    }

}
