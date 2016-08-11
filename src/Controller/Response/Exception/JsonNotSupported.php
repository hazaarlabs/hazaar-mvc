<?php

namespace Hazaar\Controller\Response\Exception;

class JsonNotSupported extends \Hazaar\Exception {

    function __construct() {

        parent::__construct("The json_encode() PHP function was not found.  Make sure that your PHP installation has JSON support.", 500);

    }

}
