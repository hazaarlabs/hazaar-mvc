<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Exception;

class Schema extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct('Schema exception: '.$message, 1);
    }
}
