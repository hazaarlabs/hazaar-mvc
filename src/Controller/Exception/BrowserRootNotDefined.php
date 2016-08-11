<?php

namespace Hazaar\Controller\Exception;

class BrowserRootNotDefined extends \Hazaar\Exception {

    function __construct() {

        parent::__construct("The internal file browser root path is not defined in the application configuration.", 503);

    }

}