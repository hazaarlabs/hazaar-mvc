<?php

namespace Hazaar\Controller\Response\Exception;

class NoLessSupport extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Less CSS files are not currently supported!  Please install leafo/lessphp with Composer.');

    }

}
