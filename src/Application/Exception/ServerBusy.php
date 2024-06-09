<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

class ServerBusy extends \Exception
{
    public function __construct()
    {
        parent::__construct('Server is too busy.  Please try again later.', 503);
    }
}
