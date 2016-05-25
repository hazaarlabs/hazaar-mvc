<?php

namespace Hazaar\Model\Exception;

class FieldUndefined extends \Hazaar\Exception {
    
    function __construct($key){
        
        parent::__construct("Trying to set value of undefined fields is not allowed ($key)");
        
    }
    
}
