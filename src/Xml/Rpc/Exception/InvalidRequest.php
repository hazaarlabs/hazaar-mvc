<?php

namespace Hazaar\Xml\Rpc\Exception;

class InvalidRequest extends \Hazaar\Exception {

    function __construct($remote) {

        parent::__construct("Invalid XMLRPC request received from $remote.");

    }

}
