<?php

declare(strict_types=1);

namespace Hazaar\Application\Router;

use Hazaar\Application\Request;

abstract class Loader
{
    /**
     * @var array<mixed>
     */
    protected array $config;

    /**
     * Loader constructor.
     *
     * @param array<mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    abstract public function exec(Request $request): bool;
}
