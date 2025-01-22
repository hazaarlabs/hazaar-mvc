<?php

namespace Hazaar\RateLimiter;

abstract class Backend implements Interface\Backend
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
