<?php

namespace Hazaar\Logger\Backend\Exception;

class NoMongoDBHost extends \Hazaar\Exception {
    
    function __construct(){
        
        parent::__construct('To use the MongoDB logger backend you must specify at least one database host!');
        
    }
    
}
