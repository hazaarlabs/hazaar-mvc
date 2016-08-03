<?php

namespace Hazaar\Controller\Response;

class Javascript extends File {

    function __construct($source = NULL) {

        parent::__construct($source);

        $this->setContentType('application/javascript');

    }

}