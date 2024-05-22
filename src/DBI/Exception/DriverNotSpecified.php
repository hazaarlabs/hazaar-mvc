<?php

declare(strict_types=1);

namespace Hazaar\DBI\Exception;

class DriverNotSpecified extends \Exception
{
    public function __construct()
    {
        parent::__construct('No database driver specified!');
    }
}
