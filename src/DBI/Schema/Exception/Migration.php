<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Exception;

class Migration extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct('Migration exception: '.$message, 2);
    }
}
