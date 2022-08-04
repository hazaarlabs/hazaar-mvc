<?php
/**
 * @file        Hazaar/Application/Request/Cli.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
 
namespace Hazaar\Application\Request;

class Cli extends \Hazaar\Application\Request {

    private $args = [];

    function init($args) {

        $this->args = $args;

    }

}