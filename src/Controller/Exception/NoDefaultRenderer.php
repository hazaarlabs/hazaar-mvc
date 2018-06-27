<?php

namespace Hazaar\Controller\Exception;

class NoDefaultRenderer extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Could not load default view renderer!', 500);

    }

}
