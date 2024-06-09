<?php

declare(strict_types=1);

namespace Hazaar\Cache\Backend\Exception;

class NoAPC extends \Exception
{
    public function __construct()
    {
        parent::__construct('The APC (Alternative PHP Cacher) extension for PHP5 is required to be able to use the APC cache backend.');
    }
}
