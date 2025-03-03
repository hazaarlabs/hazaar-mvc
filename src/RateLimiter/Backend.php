<?php

namespace Hazaar\RateLimiter;

use Hazaar\RateLimiter\Interface\Backend as BackendInterface;

abstract class Backend implements BackendInterface
{
    protected int $windowLength;
    protected bool $commit = false;

    public function __destruct()
    {
        if (true === $this->commit) {
            $this->shutdown();
        }
    }

    public function setWindowLength(int $windowLength): void
    {
        $this->windowLength = $windowLength;
    }

    public function commit(): void
    {
        $this->commit = true;
    }
}
