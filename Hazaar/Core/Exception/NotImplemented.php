<?php

namespace Hazaar\Exception;

class NotImplemented extends \Hazaar\Exception {

    function __construct($module = null) {

        $msg = 'Not implemented';

        if($module) {
            
            $msg = $module . ' is not implemented';

        }

        parent::__construct($msg);

    }

}
