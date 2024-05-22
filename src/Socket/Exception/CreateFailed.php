<?php

declare(strict_types=1);

namespace Hazaar\Socket\Exception;

class CreateFailed extends \Exception
{
    public function __construct()
    {
        parent::__construct('socket_create() failed', 500);
    }
}
