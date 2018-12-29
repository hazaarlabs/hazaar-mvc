<?php

namespace Hazaar\Cache\Backend\Exception;

class NoZlib extends \Hazaar\Exception {

    function __construct($key) {

        parent::__construct("ZLib compression is not available while readying cache key '$key'");

    }

}
