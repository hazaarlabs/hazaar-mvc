<?php
/**
 * @file        Hazaar/Application/Request/Cli.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
 
namespace Hazaar\Application\Request;

class Cli extends \Hazaar\Application\Request {

    private $args = array();

    function init($args) {

        $this->args = $args;

    }

}