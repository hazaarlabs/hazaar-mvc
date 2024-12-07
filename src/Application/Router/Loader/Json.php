<?php

declare(strict_types=1);

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Config;
use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Loader;

/**
 * JSON Router.
 *
 * The JSON router is a simple router that reads routes from a JSON file.  The JSON file should contain an array of
 * route objects.  Each route object should contain the following
 *
 * - route: The route path to match.  This can contain placeholders for arguments.  For example, /user/{id} would match
 *         /user/123 and /user/abc.  The matched values are passed as arguments to the action.
 * - regex: A regular expression to match the route path.  This can be used instead of the route key.
 * - controller: The controller to use for the route.  If not specified, the default controller is used.
 * - action: The action to use for the route.  If not specified, the default action is used.
 * - args: An array of additional arguments to pass to the action.
 * - cache: If true, the action result will be cached.  The cache key is the controller and action name with the
 *         arguments appended.  The cache TTL is specified in the ttl key.
 * - ttl: The time-to-live for the cache.
 * - method: The HTTP method to match.  If not specified, the route will match any method.
 *
 *
 * Example JSON route file:
 *
 * ```json
 * {
 *    "routes": [
 *       {
 *         "route": "/user/{id}",
 *         "controller": "user",
 *         "action": "view"
 *       },
 *       {
 *         "regex": "/user/([0-9]+)",
 *         "controller": "user",
 *         "action": "view"
 *       },
 *       {
 *         "route": "/user/{id}/edit",
 *         "controller": "user",
 *         "action": "edit"
 *       }
 *    ]
 * }
 * ```
 *
 * In the above example, the first route will match /user/123 and /user/abc and pass the matched value as an argument to
 * the view action of the user controller.  The second route will match /user/123 and pass the matched value as an argument
 * to the view action of the user controller.  The third route will match /user/123/edit and pass 123 as an argument to the
 * edit action of the user controller.
 */
class Json extends Loader
{
    public function initialise(Router $router): bool
    {
        return true;
    }

    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @return Route the route object or null if no route was matched
     */
    public function evaluateRequest(Request $request): ?Route
    {
        $jsonRouterFile = $this->config['file'] ?? 'route.json';
        $routeFile = Config::getInstance($jsonRouterFile);
        if (!isset($routeFile['routes'])) {
            throw new \Exception('Invalid JSON route file.  Missing "routes" key.');
        }
        $routes = $routeFile['routes'];
        foreach ($routes as $route) {
            if (!isset($route['route'])) {
                continue;
            }
            $controller = 'Application\Controller\\'.ucfirst($route['controller'] ?? $this->config['controller'] ?? 'index');
            $action = $route['action'] ?? $this->config['action'] ?? 'index';
            $args = $route['args'] ?? [];
            Router::match($route['methods'] ?? ['GET'], $route['route'], [$controller, $action, $args]);
        }

        return null;
    }
}
