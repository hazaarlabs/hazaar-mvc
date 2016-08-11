<?php

namespace Hazaar\Xml\Rpc\Exception;

class XMLRPCNotInstalled extends \Hazaar\Exception {

    function __construct() {

        parent::__construct("You will need to load the 'xmlrpc' PHP extension to use the XMLRPC classes.");

    }

}
