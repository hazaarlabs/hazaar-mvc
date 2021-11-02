<?php

namespace Hazaar\Mail\Transport\Exception;

/**
 * FailConnect short summary.
 *
 * FailConnect description.
 *
 * @version 1.0
 * @author jamiec
 */
class FailConnect extends \Hazaar\Exception {

    public function __construct($message, $type){

        parent::__construct("Mail Transport Error #$type: $message");

    }

}