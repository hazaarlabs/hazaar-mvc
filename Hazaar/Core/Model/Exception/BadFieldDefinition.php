<?php

namespace Hazaar\Model\Exception;

class BadFieldDefinition extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('Strict model init method must return a valid field definition');
        
    }
    
}
