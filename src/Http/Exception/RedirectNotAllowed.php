<?php

namespace Hazaar\Http\Exception;

class RedirectNotAllowed extends \Hazaar\Exception {
    
    function __construct($status){
        
        parent::__construct('Received a ' . $status . ' redirection on a non-GET request.');
        
    }
    
}
