<?php

namespace Hazaar\Cache\Exception;

class NoBackendAvailable extends \Hazaar\Exception {

    function __construct() {
    
        parent::__construct("None of the requested cache backends are currently available.");
        
    }
    
}