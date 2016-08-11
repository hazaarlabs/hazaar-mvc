<?php
namespace Hazaar\Controller\Action\Helper\Exception;

class InvalidActionController extends \Hazaar\Exception {

    function __construct($controller) {

        parent::__construct('These are called ACTION helpers for a reason.  ie: they will not work with ' . $controller);

    }

}