<?php

namespace Hazaar\Exception;

class PropertyUndefined extends \Hazaar\Exception {
    
    function __construct($class, $property){
        
        parent::__construct('Undefined property: ' . $class . '::$' . $property);
        
    }
    
}
