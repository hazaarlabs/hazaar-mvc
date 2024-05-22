<?php

declare(strict_types=1);

namespace Hazaar\DBI\Exception;

class NotConfigured extends \Exception
{
    public function __construct()
    {
        parent::__construct('The DBI adapter has not been configured!');
    }
}
