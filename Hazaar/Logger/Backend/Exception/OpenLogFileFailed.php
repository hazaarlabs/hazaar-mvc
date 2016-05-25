<?php

namespace Hazaar\Logger\Backend\Exception;

class OpenLogFileFailed extends \Hazaar\Exception {
    
    function __construct($file){
        
        parent::__construct("Unable to open log file '$file'.");
        
    }
    
}
