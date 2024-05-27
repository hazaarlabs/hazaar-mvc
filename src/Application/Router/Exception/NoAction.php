<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

use Hazaar\Exception;

class NoAction extends Exception
{
    public function __construct(string $controller)
    {
        parent::__construct("Route has no action for controller '$controller'", 404);
    }
}
