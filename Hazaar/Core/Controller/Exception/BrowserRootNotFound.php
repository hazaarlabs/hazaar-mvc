<?php

namespace Hazaar\Controller\Exception;

class BrowserRootNotFound extends \Hazaar\Exception {

    function __construct() {

        parent::__construct("File browser root path is not found!", 404);

    }

}