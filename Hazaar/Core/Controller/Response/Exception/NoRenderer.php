<?php
namespace Hazaar\Controller\Response\Exception;

class NoRenderer extends \Hazaar\Exception {

    function __construct($renderer) {

        parent::__construct("A renderer could not be found for type '$renderer'");

    }

}
