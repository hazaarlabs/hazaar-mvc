<?php

namespace Hazaar\Model\Exception;

class InitMissing extends \Hazaar\Exception {
    
    function __construct($class){
        
        parent::__construct('Missing required method init() in ' . $class);
        
    }
    
}
