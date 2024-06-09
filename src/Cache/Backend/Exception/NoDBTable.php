<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class NoDBTable extends \Exception
{
    public function __construct()
    {
        parent::__construct('A cache table is required when using the Database cache backend');
    }
}
