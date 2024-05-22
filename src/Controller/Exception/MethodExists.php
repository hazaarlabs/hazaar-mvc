<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

use Hazaar\Exception;

class MethodExists extends Exception
{
    public function __construct(string $method_name)
    {
        parent::__construct("Error trying to register controller method '{$method_name}'.  A method with that name already exist.");
    }
}
