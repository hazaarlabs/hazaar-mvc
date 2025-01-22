<?php

declare(strict_types=1);

namespace Hazaar\Controller\Interface;

use Hazaar\Application\Route;
use Hazaar\Controller\Response;

interface Controller
{
    public function run(?Route $route = null): Response;

    /**
     * @param array<int|string,mixed> $actionArgs
     */
    public function runAction(string $actionName, array $actionArgs = []): false|Response;
}
