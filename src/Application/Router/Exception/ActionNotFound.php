<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

use Hazaar\Exception;

class ActionNotFound extends Exception
{
    public function __construct(string $controller, string $action)
    {
        parent::__construct("Controller '{$controller}' has no action '{$action}'", 404);
    }
}
