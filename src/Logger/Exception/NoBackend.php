<?php

namespace Hazaar\Logger\Exception;

class NoBackend extends \Exception {
    
    function __construct(){
        
        parent::__construct('Failed to load logging backend!');
        
    }
    
}
