<?php

declare(strict_types=1);

namespace Hazaar\Logger\Interfaces;

interface Backend
{
    public function write(string $message, int $level = LOG_INFO, ?string $tag = null): void;

    public function trace(): void;

    public function postRun(): void;
}
