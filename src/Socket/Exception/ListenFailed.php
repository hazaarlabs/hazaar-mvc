<?php

namespace Hazaar\Socket\Exception;

class ListenFailed extends \Exception {

    function __construct($socket) {

        $reason = socket_strerror(socket_last_error($socket));
        
        parent::__construct('socket_listen() failed.  Reason: ' . $reason, 500);
    
    }

}