<?php

namespace Hazaar\Cache\Exception;

class NoBackendAvailable extends \Hazaar\Exception {

    function __construct($backends = []) {
    
        $msg = "None of the requested cache backends are currently available.";

        if(count($backends) > 0)
            $msg .= " Requested backends: " . implode(', ', $backends);
        
        parent::__construct($msg);
        
    }
    
}