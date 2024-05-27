<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Exception;

use Hazaar\Exception;

class RouteFailed extends Exception
{
    public function __construct(string $route)
    {
        parent::__construct('Failed to process route: '.$route, 404);
    }
}
