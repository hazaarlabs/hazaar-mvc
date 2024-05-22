<?php

declare(strict_types=1);

namespace Hazaar\DBI\Exception;

class DriverNotFound extends \Exception
{
    public function __construct(string $driver)
    {
        parent::__construct("Database driver '{$driver}' not found.");
    }
}
