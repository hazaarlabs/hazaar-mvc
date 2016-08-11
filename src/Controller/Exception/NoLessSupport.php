<?php

namespace Hazaar\Controller\Exception;

class NoLessSupport extends \Hazaar\Exception {

    function __construct() {

        parent::__construct('Less CSS files are not currently supported!  Missing Support/LessPHP/lessc.inc.php');

    }

}
