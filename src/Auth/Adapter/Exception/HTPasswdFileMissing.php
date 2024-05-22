<?php

declare(strict_types=1);

namespace Hazaar\Auth\Adapter\Exception;

use Hazaar\Exception;

class HTPasswdFileMissing extends Exception
{
    public function __construct(string $file)
    {
        parent::__construct('HTPasswd file not found: '.$file);
    }
}
