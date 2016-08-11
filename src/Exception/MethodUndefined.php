<?php

namespace Hazaar\Exception;

class MethodUndefined extends \Hazaar\Exception {
    
    function __construct($class, $method){
        
        parent::__construct('Call to undefined method ' . $class . '::' . $method . '()');
        
    }
    
}
