<?php

declare(strict_types=1);

namespace Hazaar\Socket\Exception;

class BindFailed extends \Exception
{
    public function __construct(\Socket $socket)
    {
        $reason = socket_strerror(socket_last_error($socket));
        parent::__construct('socket_bind() failed.  Reason: '.$reason, 500);
    }
}
