<?php

namespace Hazaar\Http\Exception;

class HostNotFound extends \Hazaar\Exception {
    
    function __construct($host){
        
        parent::__construct("Host '$host' not found.  Please check the address and try again.");
        
    }
    
}
