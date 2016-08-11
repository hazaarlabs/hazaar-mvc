<?php

namespace Hazaar\Controller\Response;

class Stream extends \Hazaar\Controller\Response\HTTP\OK {

    function __construct() {

        parent::__construct("text/plain");

    }

    public function __writeoutput() {

        ob_flush();

        flush();

        return;

    }

}
