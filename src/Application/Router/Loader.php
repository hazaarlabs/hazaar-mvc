<?php

declare(strict_types=1);

namespace Hazaar\Application\Router;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;

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

    /**
     * Initialises the basic router.
     *
     * @return bool returns true if the initialisation is successful, false otherwise
     */
    abstract public function initialise(Router $router): bool;

    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @param Request $request the request object
     *
     * @return Route returns the route object if the evaluation is successful, null otherwise
     */
    public function evaluateRequest(Request $request): ?Route
    {
        return null;
    }
}
