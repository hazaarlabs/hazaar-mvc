<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

use Hazaar\Exception;

class ClientException extends Exception
{
    /**
     * @param array<string, mixed> $xmlrpc_fault
     */
    public function __construct(array $xmlrpc_fault)
    {
        parent::__construct(ake($xmlrpc_fault, 'faultString'), ake($xmlrpc_fault, 'faultCode'));
        if ($file = ake($xmlrpc_fault, 'faultFile')) {
            $this->file = $file;
        }
        if ($line = ake($xmlrpc_fault, 'faultLine')) {
            $this->line = $line;
        }
    }
}
