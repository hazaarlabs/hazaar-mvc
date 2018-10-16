<?php

namespace Hazaar\Controller\Response;

class Text extends \Hazaar\Controller\Response {

    function __construct($content = null, $status = 200) {

        parent::__construct("text/plain", $status);

        $this->setContent($content);

    }

}
