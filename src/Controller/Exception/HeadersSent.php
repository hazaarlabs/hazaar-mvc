<?php

namespace Hazaar\Controller\Exception;

class HeadersSent extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Headers already sent while trying to render controller response.  Make sure your response uses $this->setHeader().');

    }

}
