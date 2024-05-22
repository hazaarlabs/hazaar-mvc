<?php

declare(strict_types=1);

namespace Hazaar\File\Exception;

use Hazaar\Exception;

class SaveFailed extends Exception
{
    public function __construct(string $key)
    {
        parent::__construct("Can not save uploaded file at '{$key}'.  Requested key does not exist!");
    }
}
