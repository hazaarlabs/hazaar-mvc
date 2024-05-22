<?php

declare(strict_types=1);

namespace Hazaar\Logger\Interfaces;

interface Backend
{
    public function write(string $tag, string $message, int $level = LOG_NOTICE): void;

    public function trace(): void;

    public function postRun(): void;
}
