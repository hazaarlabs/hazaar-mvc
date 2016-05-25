<?php

namespace Hazaar\Cache\Exception;

class NoFunction extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Cached function call attempted without specifying a function to call!');

    }

}
