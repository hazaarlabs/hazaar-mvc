<?php

namespace Hazaar\Exception;

class ExtenderInvokeFailed extends \Hazaar\Exception {
    
    function __construct($access, $class, $method, $parent){
        
        parent::__construct("Trying to invoke $access method {$class}::{$method}() from scope $parent.");
        
    }
    
}
