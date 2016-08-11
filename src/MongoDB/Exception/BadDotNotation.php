<?php

namespace Hazaar\MongoDB\Exception;

class BadDotNotation extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Attempted to resolve singular dot notation value without prefix.  You can\'t do that!');

    }

}

