<?php

namespace Hazaar\File\Exception;

class SaveFailed extends \Hazaar\Exception {

    function __construct($key) {

        parent::__construct("Can not save uploaded file at '$key'.  Requested key does not exist!");

    }

}
