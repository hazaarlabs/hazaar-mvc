<?php

namespace Hazaar\Exception;

class ExtenderMayNotInherit extends \Hazaar\Exception {
    
    function __construct($type, $child, $class){
        
        parent::__construct("Class '$child' may not inherit from $type class '$class'.");
        
    }
    
}
