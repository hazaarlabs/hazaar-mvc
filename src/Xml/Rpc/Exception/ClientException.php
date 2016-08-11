<?php

namespace Hazaar\Xml\Rpc\Exception;

class ClientException extends \Hazaar\Exception {

    function __construct($xmlrpc_fault) {

        parent::__construct(ake($xmlrpc_fault, 'faultString'), ake($xmlrpc_fault, 'faultCode'));

        if($file = ake($xmlrpc_fault, 'faultFile'))
            $this->file = $file;

        if($line = ake($xmlrpc_fault, 'faultLine'))
            $this->line = $line;

    }

}
