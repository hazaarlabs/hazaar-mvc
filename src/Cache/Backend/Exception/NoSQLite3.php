<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class NoSQLite3 extends \Exception
{
    public function __construct()
    {
        parent::__construct('The SQLite cache backend requires the SQLite3 PHP extension to be loaded.');
    }
}
