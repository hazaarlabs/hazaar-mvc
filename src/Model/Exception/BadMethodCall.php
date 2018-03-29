<?php

namespace Hazaar\Model\Exception;

class BadMethodCall extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('Strict model init method must return a valid field definition');
        
    }
    
}
