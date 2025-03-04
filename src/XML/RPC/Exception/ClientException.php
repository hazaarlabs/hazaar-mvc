<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

class ClientException extends \Exception
{
    /**
     * @param array<string, mixed> $xmlrpc_fault
     */
    public function __construct(array $xmlrpc_fault)
    {
        parent::__construct($xmlrpc_fault['faultString'] ?? 'Unknown', $xmlrpc_fault['faultCode'] ?? 0);
        if ($file = ($xmlrpc_fault['faultFile'] ?? null)) {
            $this->file = $file;
        }
        if ($line = ($xmlrpc_fault['faultLine'] ?? null)) {
            $this->line = $line;
        }
    }
}
