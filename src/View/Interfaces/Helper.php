<?php

declare(strict_types=1);

namespace Hazaar\View\Interfaces;

/**
 * Base view helper interface.
 */
interface Helper
{
    /**
     * @param array<string, mixed> $args
     */
    public function init(array $args = []): bool;
}
