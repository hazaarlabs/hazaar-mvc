<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

class RouteNotFound extends \Exception
{
    public function __construct(string $path)
    {
        $path = ltrim($path, '/');
        parent::__construct("No route found to handle '/{$path}'.", 404);
    }
}
