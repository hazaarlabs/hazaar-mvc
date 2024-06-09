<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class InternalFileNotFound extends \Exception
{
    public function __construct(string $file)
    {
        parent::__construct("Internal file not found while requesting file '{$file}'", 404);
    }
}
