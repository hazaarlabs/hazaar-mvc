<?php

namespace Hazaar\File\Exception;

class SourceNotFound extends \Hazaar\Exception {

    function __construct($source, $target) {

        parent::__construct("Source file '$source' does not exist while copying to '$target'.");

    }

}