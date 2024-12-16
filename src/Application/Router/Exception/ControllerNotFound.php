<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class ControllerNotFound extends \Exception
{
    public function __construct(string $controllerClass, string $route)
    {
        parent::__construct("Controller {$controllerClass} not found handling route {$route}");
    }
}
