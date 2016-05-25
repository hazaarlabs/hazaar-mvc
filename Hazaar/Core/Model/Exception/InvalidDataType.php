<?php

namespace Hazaar\Model\Exception;

class InvalidDataType extends \Hazaar\Exception {

    function __construct($expected, $got) {

        parent::__construct("Invalid data type. Expecting an object of type '$expected' but got object of type $got and could not convert it automatically.");

    }

}

