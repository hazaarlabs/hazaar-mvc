<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class ResponseInvalid extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid controller response received.  Controllers should return an object type Hazaar\\Controller\\Response.');
    }
}
