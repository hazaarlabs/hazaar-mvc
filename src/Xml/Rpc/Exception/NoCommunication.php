<?php

namespace Hazaar\Xml\Rpc\Exception;

class NoCommunication extends \Hazaar\Exception {

    function __construct($url) {

        parent::__construct('Error communicating with ' . $url . '.  Check the address and try again.');


    }

}
