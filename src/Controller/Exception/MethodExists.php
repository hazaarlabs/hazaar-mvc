<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class MethodExists extends \Exception
{
    public function __construct(string $methodName)
    {
        parent::__construct("Error trying to register controller method '{$methodName}'.  A method with that name already exist.");
    }
}
