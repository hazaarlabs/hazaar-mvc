<?php

namespace Hazaar\Socket\Exception;

class OptionFailed extends \Exception {

    function __construct($socket) {

        $reason = socket_strerror(socket_last_error($socket));
        
        parent::__construct('socket_set_option() failed.  Reason: ' . $reason, 500);
    
    }

}