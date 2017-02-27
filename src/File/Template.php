<?php

namespace Hazaar\File;

class Template extends \Hazaar\View\Template {

    function __construct($filename){

        parent::__construct();

        if($filename)
            $this->loadFromFile($filename);

    }

}
