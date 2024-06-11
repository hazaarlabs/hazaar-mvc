<?php

declare(strict_types=1);

namespace Hazaar\Auth\Storage\Exception;

class SessionStartFailed extends \Exception
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Failed to start session', 500, $previous);
    }
}
