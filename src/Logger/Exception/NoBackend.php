<?php

namespace Hazaar\Logger\Exception;

class NoBackend extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('Failed to load logging backend!');
        
    }
    
}
