<?php

namespace Hazaar\Controller\Response;

class Text extends \Hazaar\Controller\Response\HTTP\OK {

    function __construct($content = null) {

        parent::__construct("text/plain");

        $this->setContent($content);

    }

}
