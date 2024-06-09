<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class NoDBConfig extends \Exception
{
    public function __construct()
    {
        parent::__construct('The Database cache backend requires database configuration parameters.');
    }
}
