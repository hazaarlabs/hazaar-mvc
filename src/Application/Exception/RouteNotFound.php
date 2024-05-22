<?php

declare(strict_types=1);

namespace Hazaar\Application\Exception;

use Hazaar\Exception;

class RouteNotFound extends Exception
{
    protected string $name = 'Route Not Found';

    public function __construct(string $path)
    {
        parent::__construct("No route found to handle '{$path}'.", 404);
    }
}
