<?php

namespace Hazaar\Controller\Response\HTTP;

class BadRequest extends \Hazaar\Controller\Response {

    function __construct($content_type = "text/html") {

        parent::__construct($content_type, 400);

    }

}