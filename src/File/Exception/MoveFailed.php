<?php

namespace Hazaar\File\Exception;

class MoveFailed extends \Hazaar\Exception {

    function __construct($dest_file) {

        parent::__construct("Unable to move uploaded file.  Destination file already exists at '$dest_file'.  Use the \$overwrite parameter if your want to overwrite the file.");

    }

}
