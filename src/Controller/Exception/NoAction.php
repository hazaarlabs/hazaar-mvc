<?php

declare(strict_types=1);

namespace Hazaar\Controller\Exception;

class NoAction extends \Exception
{
    public function __construct(string $controller)
    {
        parent::__construct("Controller '{$controller}' has no runnable action", 404);
    }
}
