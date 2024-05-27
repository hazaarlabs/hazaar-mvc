<?php

declare(strict_types=1);

namespace Hazaar\Controller\Interfaces;

use Hazaar\Controller\Response;

interface Controller
{
    public function __run(): false|Response;

    /**
     * @param array<int|string,mixed> $actionArgs
     */
    public function __runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response;
}
