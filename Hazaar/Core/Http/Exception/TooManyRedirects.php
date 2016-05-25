<?php

namespace Hazaar\Http\Exception;

class TooManyRedirects extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('Request is not redirecting correctly.  Too many redirect attempts.');
        
    }
    
}
