<?php
namespace Hazaar\Controller\Exception;

class NoContent extends \Hazaar\Exception {

    function __construct($class) {

        parent::__construct('The view renderer did not produce any content while rendering ' . $class);

    }

}