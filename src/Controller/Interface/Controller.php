<?php

declare(strict_types=1);

namespace Hazaar\Controller\Interface;

use Hazaar\Application\Route;
use Hazaar\Controller\Response;

interface Controller
{
    /**
     * Evaluate a route and run the controller.
     */
    public function run(?Route $route = null): Response;
}
