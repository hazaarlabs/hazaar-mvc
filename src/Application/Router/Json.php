<?php

declare(strict_types=1);

namespace Hazaar\Application\Router;

use Hazaar\Application\Config;
use Hazaar\Application\Request;
use Hazaar\Application\Request\HTTP;
use Hazaar\Application\Router;
use Hazaar\Exception;
use Hazaar\Map;

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
class Json extends Router
{
    /**
     * Matches the given route path with the provided path and populates the action arguments.
     *
     * @param Map           $route      the route to match against
     * @param string        $path       the path to match
     * @param array<string> $actionArgs the array to populate with action arguments
     *
     * @return bool returns true if the route path matches, false otherwise
     */
    private function __matchRoutePath(Map $route, string $path, array &$actionArgs = []): bool
    {
        if ($route->has('route')) {
            $path = explode('/', trim($path, '/'));
            $routePath = explode('/', trim($route->get('route'), '/'));
            if (count($path) !== count($routePath)) {
                return false;
            }
            for ($i = 0; $i < count($path); ++$i) {
                if ($routePath[$i] === $path[$i] || '*' === $routePath[$i]) {
                    continue;
                }
                if ('{' === substr($routePath[$i], 0, 1) && '}' === substr($routePath[$i], -1)) {
                    if (false !== strpos($routePath[$i], ':')) {
                        list($type, $key) = explode(':', substr($routePath[$i], 1, -1));
                        if (('int' === $type || 'integer' === $type
                            || 'float' === $type || 'double' === $type)
                            && !is_numeric($path[$i])) {
                            return false;
                        }
                        if ('bool' === $type || 'boolean' === $type) {
                            $path[$i] = boolify($path[$i]);
                        } elseif ('array' === $type) {
                            $path[$i] = explode(',', $path[$i]);
                        } elseif ('json' === $type) {
                            $path[$i] = json_decode($path[$i], true);
                        } else {
                            settype($path[$i], $type);
                        }
                        $actionArgs[$key] = $path[$i];
                    } else {
                        $actionArgs[] = $path[$i];
                    }

                    continue;
                }

                return false;
            }

            return true;
        }
        if ($route->has('regex')) {
            if (1 === preg_match('!'.$route->get('regex', '/').'!', $path, $matches)) {
                $actionArgs = array_slice($matches, 1);

                return true;
            }
        }

        return false;
    }

    /**
     * Evaluates the request and sets the controller, action, and arguments based on the request path.
     *
     * @param Request $request the request object
     *
     * @return bool returns true if the evaluation is successful, false otherwise
     */
    public function evaluateRequest(Request $request): bool
    {
        $jsonRouterFile = $this->config->get('file', 'routes.json');
        $routeFile = new Config('routes.json');
        if (!$routeFile->has('routes')) {
            throw new \Exception('Invalid JSON route file.  Missing "routes" key.');
        }
        $path = '/'.ltrim($request->getPath(), '/');
        if ('/' === $path) {
            return true;
        }
        $routes = $routeFile->get('routes');
        $method = $request instanceof HTTP ? $request->getMethod() : 'GET';
        foreach ($routes as $route) {
            if ($route->has('method') && strtoupper($route->get('method')) !== $method) {
                continue;
            }
            $actionArgs = [];
            if (false === $this->__matchRoutePath($route, $path, $actionArgs)) {
                continue;
            }
            if ($route->has('controller')) {
                $this->controller = $route['controller'];
            }
            if ($route->has('action')) {
                $this->action = $route['action'];
            }
            $this->actionArgs = $route->has('args')
                ? array_merge($actionArgs, $route['args']->toArray())
                : $actionArgs;
            if (true === $route->get('cache', false)) {
                $this->cacheAction($this->controller, $this->action, $route->get('ttl', 60));
            }

            return true;
        }

        return false;
    }
}
