<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class RedisError extends \Exception
{
    public function __construct(string $message)
    {
        if ('-ERR' == substr($message, 0, 4)) {
            $message = substr($message, 5);
        }

        parent::__construct($message);
    }
}
