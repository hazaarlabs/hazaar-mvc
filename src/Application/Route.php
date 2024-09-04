<?php

namespace Hazaar\Application;

class Route
{
    private mixed $callback;

    public function __construct(mixed $callback)
    {
        $this->callback = $callback;
    }
}
