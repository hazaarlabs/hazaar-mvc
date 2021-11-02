<?php

namespace Hazaar\Socket\Exception;

class CreateFailed extends \Exception {

    function __construct($socket) {

        $reason = socket_strerror(socket_last_error($socket));
        
        parent::__construct('socket_create() failed.  Reason: ' . $reason, 500);
    
    }

}