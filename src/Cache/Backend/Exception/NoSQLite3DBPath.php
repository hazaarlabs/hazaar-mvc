<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class NoSQLite3DBPath extends \Exception
{
    public function __construct()
    {
        parent::__construct('A cache DB path is required when using the SQlite cache backend');
    }
}
