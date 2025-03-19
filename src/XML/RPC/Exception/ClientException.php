<?php

declare(strict_types=1);

namespace Hazaar\XML\RPC\Exception;

class ClientException extends \Exception
{
    /**
     * @param array<string, mixed> $xmlrpcFault
     */
    public function __construct(array $xmlrpcFault)
    {
        parent::__construct($xmlrpcFault['faultString'] ?? 'Unknown', $xmlrpcFault['faultCode'] ?? 0);
        if ($file = ($xmlrpcFault['faultFile'] ?? null)) {
            $this->file = $file;
        }
        if ($line = ($xmlrpcFault['faultLine'] ?? null)) {
            $this->line = $line;
        }
    }
}
