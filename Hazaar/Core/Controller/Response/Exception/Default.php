<?php
namespace Hazaar\Controller\Response\Exception;

class ActionNotFound extends \Hazaar\Exception {

    function __construct() {

        parent::__construct("Controller '$controller' does not have the action '$action'.", 404);

    }

}
