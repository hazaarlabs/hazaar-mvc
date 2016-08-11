<?php

namespace Hazaar\Http\Exception;

class ProtocolPortUnknown extends \Hazaar\Exception {
    
    function __construct($proto){
        
        parent::__construct("Unable to find port for protocol '$proto'.  Please make sure your protocol is correct, and if so you may need to specify a port.");
        
    }
    
}
