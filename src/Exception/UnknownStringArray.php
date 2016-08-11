<?php

namespace Hazaar\Exception;

class UnknownStringArray extends \Hazaar\Exception {
    
    function __construct($defaults){
        
        parent::__construct('Unknown string array format! Got: "' . $defaults . '"');
        
    }
    
}
