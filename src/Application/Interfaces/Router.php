<?php

namespace Hazaar\Application\Interfaces;

use Hazaar\Application\Request;

interface Router
{
    public function evaluateRequest(Request $request): bool;

    public function getControllerName(): ?string;

    public function getActionName(): ?string;

    /**
     * @return array<string>
     */
    public function getActionArgs(): array;
}
