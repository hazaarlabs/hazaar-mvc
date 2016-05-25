<?php

namespace Hazaar\Controller\Exception;

class ActionNotFound extends \Hazaar\Exception {

    function __construct($controller, $action) {

        parent::__construct("Controller '$controller' does not have the action '$action'.", 404);

    }

}
