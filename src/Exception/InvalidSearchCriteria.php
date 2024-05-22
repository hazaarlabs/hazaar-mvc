<?php

declare(strict_types=1);

namespace Hazaar\Exception;

use Hazaar\Exception;

class InvalidSearchCriteria extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid search criteria supplied!');
    }
}
