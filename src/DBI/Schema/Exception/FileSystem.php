<?php

declare(strict_types=1);

namespace Hazaar\DBI\Schema\Exception;

class FileSystem extends \Exception
{
    public function __construct(string $message)
    {
        parent::__construct('DBI file system exception: '.$message, 5);
    }
}
