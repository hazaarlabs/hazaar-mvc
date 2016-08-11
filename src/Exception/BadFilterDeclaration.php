<?php

namespace Hazaar\Exception;

class BadFilterDeclaration extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('You MUST specify at least a callback to use output filters!');

    }

}
