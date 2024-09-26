<?php

namespace Hazaar\RateLimiter;

abstract class Backend implements Interfaces\Backend
{
    protected int $windowLength;

    public function __destruct()
    {
        $this->shutdown();
    }

    public function setWindowLength(int $windowLength): void
    {
        $this->windowLength = $windowLength;
    }
}
