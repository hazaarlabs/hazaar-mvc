<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class ResponseInvalid extends \Exception
{
    public function __construct(string $controller, string $action)
    {
        parent::__construct("Invalid controller response received from {$controller}::{$action}.");
    }
}
