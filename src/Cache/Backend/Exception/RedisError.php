<?php

namespace Hazaar\Cache\Backend\Exception;

class RedisError extends \Hazaar\Exception {

    function __construct($message) {

        if(substr($message, 0, 4) == '-ERR')
            $message = substr($message, 5);

        parent::__construct($message);

    }

}
