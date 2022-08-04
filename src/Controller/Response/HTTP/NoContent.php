<?php

namespace Hazaar\Controller\Response\HTTP;

class NoContent extends \Hazaar\Controller\Response {

    function __construct() {

        parent::__construct(null, 204);

    }

}