<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Exception;

class Datasync extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct('Data sync exception: '.$message, 4);
    }
}
