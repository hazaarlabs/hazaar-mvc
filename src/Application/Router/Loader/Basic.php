<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Loader;

/**
 * Basic Application Router.
 *
 * This router is a basic router that will evaluate the request path and set the
 * controller, action, and arguments from a simple formatted path.
 *
 * A path should be formatted as /controller/action/arg1/arg2/arg3
 *
 * This will set the controller to 'Controller', the action to 'action', and the
 * arguments to ['arg1', 'arg2', 'arg3'] which will result in the
 * \Application\Controllers\Controller::action('arg1', 'arg2', 'arg3') method being called.
 */
class Basic extends Loader
{
    /**
     * Initialises the basic router.
     *
     * @return bool returns true if the initialisation is successful, false otherwise
     */
    public function initialise(Router $router): bool
    {
        return true;
    }

    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @param Request $request the request object
     *
     * @return Route returns the route object if the evaluation is successful, null otherwise
     */
    public function evaluateRequest(Request $request): ?Route
    {
        $path = trim($request->getPath());
        if ('/' === $path) {
            return null; // Return true if the path is empty.  Allows for default controller/action to be used.
        }
        $parts = explode('/', ltrim($path, '/'));
        $controller = 'Application\Controllers\\'.(('' !== $parts[0]) ? ucfirst($parts[0]) : null);
        if (!class_exists($controller)) {
            return null;
        }
        $action = (isset($parts[1]) && '' !== $parts[1]) ? $parts[1] : null;
        $actionArgs = (count($parts) > 2) ? array_slice($parts, 2) : null;
        $route = new Route($path);
        $route->setCallable([$controller, $action, $actionArgs]);

        return $route;
    }
}
