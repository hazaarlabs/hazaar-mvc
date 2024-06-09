<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class ActionNotFound extends \Exception
{
    public function __construct(string $controller, string $action)
    {
        parent::__construct("Controller '{$controller}' does not have the action '{$action}'.", 404);
    }
}
