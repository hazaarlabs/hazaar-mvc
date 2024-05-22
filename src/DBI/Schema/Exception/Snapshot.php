<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Exception;

class Snapshot extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct('Snapshot exception: '.$message, 3);
    }
}
