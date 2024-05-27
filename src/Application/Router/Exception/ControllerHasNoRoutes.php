<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class ControllerHasNoRoutes extends \Exception
{
    public function __construct(string $controllerClass)
    {
        parent::__construct("Controller {$controllerClass} has no annotated routes");
    }
}
