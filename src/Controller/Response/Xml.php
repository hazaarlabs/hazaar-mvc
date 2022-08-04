<?php

namespace Hazaar\Controller\Response;

class Xml extends \Hazaar\Controller\Response {

    function __construct($content = NULL, $status = 200) {

        parent::__construct("text/xml", $status);

        $this->setContent($content);

    }

}