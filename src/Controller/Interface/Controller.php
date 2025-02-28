<?php

declare(strict_types=1);

namespace Hazaar\Controller\Interface;

use Hazaar\Application\Route;
use Hazaar\Controller\Response;

interface Controller
{
    /**
     * Run the controller.
     */
    public function run(): Response;

    /**
     * Evaluate a route and run the controller.
     */
    public function runRoute(?Route $route = null): Response;

    /**
     * @param array<int|string,mixed> $actionArgs
     */
    public function runAction(string $actionName, array $actionArgs = []): false|Response;
}
