<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Loader;

/**
 * None Application Router.
 *
 * This is a dummy router that does nothing.  It is used when no router is specified.  You would
 * use this router if you are using a custom entry point script that instantiates the controller
 * directly and doesn't require any routing.
 *
 * @example
 * ```php
 * $application = new Application($controller);
 * $controller = new MyController();
 * $application->run($controller);
 * ```
 */
class None extends Loader
{
    /**
     * @return bool Always returns true
     */
    public function initialise(Router $router): bool
    {
        return true;
    }

    /**
     * Evaluating the request always returns null.
     *
     * This is because the None router does not do any routing and forces the application to
     * throw a RouteNotFound exception.
     *
     * @return null|Route Always returns null
     */
    public function evaluateRequest(Request $request): ?Route
    {
        return null;
    }
}
