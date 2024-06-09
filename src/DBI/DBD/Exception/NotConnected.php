<?php

declare(strict_types=1);

namespace Hazaar\DBI\DBD\Exception;

class NotConnected extends \Exception
{
    public function __construct()
    {
        parent::__construct('PDO is not available or not connected.');
    }
}
