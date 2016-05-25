<?php

namespace Hazaar\Http\Exception;

class NoConnection extends \Hazaar\Exception {
    
    function __construct($host, $errno, $errstr){
        
        parent::__construct("Unable to connect to $host.  ERR: $errno# - $errstr");
        
    }
    
}
