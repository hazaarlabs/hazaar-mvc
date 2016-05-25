<?php

namespace Hazaar\Controller\Response;

class Xml extends \Hazaar\Controller\Response\HTTP\OK {

    function __construct($content = NULL) {

        parent::__construct("text/xml");

        $this->setContent($content);

    }

}