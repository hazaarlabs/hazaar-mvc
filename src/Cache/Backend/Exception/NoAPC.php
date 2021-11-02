<?php

namespace Hazaar\Cache\Backend\Exception;

class NoAPC extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('The APC (Alternative PHP Cacher) extension for PHP5 is required to be able to use the APC cache backend.');

    }

}
