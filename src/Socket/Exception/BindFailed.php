<?php

namespace Hazaar\Socket\Exception;

class BindFailed extends \Exception {

    function __construct($socket) {

        $reason = socket_strerror(socket_last_error($socket));
        
        parent::__construct('socket_bind() failed.  Reason: ' . $reason, 500);
    
    }

}