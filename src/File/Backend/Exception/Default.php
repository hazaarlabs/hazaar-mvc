<?php

declare(strict_types=1);

namespace Hazaar\File\Backend\Exception;

use Hazaar\Exception;

class ClassNotFound extends Exception
{
    public function __construct()
    {
        parent::__construct('Message');
    }
}
