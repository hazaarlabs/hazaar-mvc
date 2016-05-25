<?php

namespace Hazaar\Controller\Exception;

class ActionNotPublic extends \Hazaar\Exception {

    function __construct($controller, $action) {

        parent::__construct("Target action $controller::$action() is not public!", 405);

    }

}
