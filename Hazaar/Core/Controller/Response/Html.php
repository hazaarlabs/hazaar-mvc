<?php

namespace Hazaar\Controller\Response;

class Html extends \Hazaar\Controller\Response {

    function __construct($status = 200) {

        parent::__construct("text/html", $status);

    }

}