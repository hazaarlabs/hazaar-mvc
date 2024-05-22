<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class FileNotFound extends Exception
{
    public function __construct(string $filename)
    {
        parent::__construct("Requested file '{$filename}' could not be found", 404);
    }
}
