<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class NoMemcached extends \Exception
{
    public function __construct()
    {
        parent::__construct('The memcached extension for PHP5 is required to be able to use the memcached cache backend.');
    }
}
