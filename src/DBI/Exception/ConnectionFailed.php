<?php

declare(strict_types=1);

namespace Hazaar\DBI\Exception;

class ConnectionFailed extends \Exception
{
    /**
     * @param array<int, int|string> $error
     */
    public function __construct(string $host, ?array $error = null)
    {
        $msg = "Database connection failed connecting to host '{$host}'.";
        if ($error) {
            $msg .= ' Reason: '.$error[1];
        }
        parent::__construct($msg, 500);
    }
}
