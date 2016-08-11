<?php

namespace Hazaar\Exception;

class FileNotFound extends \Hazaar\Exception {
    
    function __construct($filename){
        
        parent::__construct("Requested file '$filename' could not be found", 404);
        
    }
    
}
