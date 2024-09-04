<?php

declare(strict_types=1);

namespace Hazaar\Application\Router;

use Hazaar\Application\Request;
use Hazaar\Map;

abstract class Loader
{
    protected Map $config;

    public function __construct(Map $config)
    {
        $this->config = $config;
    }

    abstract public function loadRoutes(Request $request): bool;
}
