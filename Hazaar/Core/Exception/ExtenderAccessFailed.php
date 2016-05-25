<?php

namespace Hazaar\Exception;

class ExtenderAccessFailed extends \Hazaar\Exception {
    
    function __construct($access, $class, $property){
        
        parent::__construct('Cannot access $access property ' . $class . '::$' . $property);
        
    }
    
}
