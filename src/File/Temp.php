<?php

namespace Hazaar\File;

/**
 * Temp short summary.
 *
 * Temp description.
 *
 * @version 1.0
 * @author JamieCarl
 */
class Temp extends \Hazaar\File {

    function __construct($name = null){

        if(!$name) $name = uniqid() . '.tmp';

        $name = \Hazaar\Application::getInstance()->runtimePath('temp', true) . DIRECTORY_SEPARATOR . $name;

        parent::__construct($name);

    }

    function __destruct(){

        $this->unlink();

    }

}