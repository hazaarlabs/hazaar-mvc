<?php

namespace Hazaar\Application\Exception;

class ServerBusy extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('Server is too busy.  Please try again later.', 503);
        
    }
    
}
