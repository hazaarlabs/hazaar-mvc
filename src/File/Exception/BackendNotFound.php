<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class BackendNotFound extends Exception
{
    public function __construct(string $backend)
    {
        parent::__construct("Unknown filesystem backend : '{$backend}'");
    }
}
