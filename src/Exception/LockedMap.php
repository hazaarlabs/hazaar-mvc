<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class LockedMap extends \Exception
{
    public function __construct()
    {
        parent::__construct('The map can not be modified because it is locked.');
    }
}
