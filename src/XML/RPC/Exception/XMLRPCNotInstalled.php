<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

use Hazaar\Exception;

class XMLRPCNotInstalled extends Exception
{
    public function __construct()
    {
        parent::__construct("You will need to load the 'xmlrpc' PHP extension to use the XMLRPC classes.");
    }
}
