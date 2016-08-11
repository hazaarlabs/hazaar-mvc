<?php

namespace Hazaar\Controller\Response\HTTP;

class Redirect extends \Hazaar\Controller\Response {

    function __construct($url) {

        parent::__construct("text/text", 302);

        $this->setHeader('Location', $url);

    }

}