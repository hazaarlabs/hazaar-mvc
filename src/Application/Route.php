<?php

namespace Hazaar\Application;

use Hazaar\Controller;
use Hazaar\Controller\Closure;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Handler;
use Hazaar\Util\Boolean;

/**
 * The route class.
 *
 * This class is used to define a route in the application. It contains the callable
 * property, which is the action to be executed
 * when the route is matched. It also contains the path, methods, and responseType
 * properties, which are used to match the route against the request path and method.
 */
#[\Attribute]
class Route
{
    public Router $router;
    private mixed $callable;
    private ?string $path = null;
    private int $responseType = Response::TYPE_HTML;

    /**
     * @var array<string>
     */
    private array $methods = [];

    /**
     * @var array<mixed>
     */
    private array $actionArgs = [];

    /**
     * @var array<string,\ReflectionParameter>
     */
    private array $callableParameters = [];

    /**
     * @var array<Handler> an array of middleware instances associated with the route
     */
    private array $middleware = [];

    /**
     * @param array<string> $methods
     */
    public function __construct(
        ?string $path = null,
        array $methods = []
    ) {
        $this->path = $path;
        $this->methods = array_map('strtoupper', $methods);
    }

    /**
     * Sets the callable for the route and processes its reflection.
     *
     * @param mixed $callable The callable to be set. It can be a closure, an array with a class and method, or a reflection method.
     *
     * @throws \ReflectionException If the callable cannot be reflected.
     *
     * The method performs the following actions:
     * - If the callable is an array and has a third element, it sets the action arguments.
     * - Sets the callable property.
     * - Uses reflection to determine the return type of the callable and sets the response type accordingly.
     * - Extracts and stores the parameters of the callable.
     */
    public function setCallable(mixed $callable): void
    {
        if (is_array($callable) && isset($callable[2])) {
            $this->actionArgs = is_array($callable[2]) ? $callable[2] : [$callable[2]];
        }
        $this->callable = $callable;

        try {
            $callableReflection = match (true) {
                $this->callable instanceof \Closure => new \ReflectionFunction($this->callable),
                $this->callable instanceof \ReflectionMethod => $this->callable,
                default => new \ReflectionMethod($this->callable[0], $this->callable[1]),
            };
            if ($callableReflection->hasReturnType()) {
                // @phpstan-ignore method.notFound
                $this->responseType = match ($callableReflection->getReturnType()->getName()) {
                    'Hazaar\Controller\Response\View' => Response::TYPE_HTML,
                    'Hazaar\Controller\Response\HTML' => Response::TYPE_HTML,
                    'Hazaar\Controller\Response\Text' => Response::TYPE_TEXT,
                    'Hazaar\Controller\Response\XML' => Response::TYPE_XML,
                    'Hazaar\Controller\Response\JSON' => Response::TYPE_JSON,
                    'Hazaar\Controller\Response\PDF' => Response::TYPE_BINARY,
                    default => Response::TYPE_HTML,
                };
            }
            foreach ($callableReflection->getParameters() as $param) {
                $this->callableParameters[$param->getName()] = $param;
            }
        } catch (\ReflectionException $e) {
            // Do nothing
        }
    }

    /**
     * Sets the router instance for the application.
     *
     * @param Router $router the router instance to be set
     */
    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    /**
     * Retrieves the path associated with the current route.
     *
     * @return null|string the path of the route, or null if no path is set
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Matches the given HTTP method and path against the route's method and path.
     *
     * @param string $method The HTTP method to match (e.g., 'GET', 'POST').
     * @param string $path   the request path to match
     *
     * @return bool returns true if the method and path match the route, false otherwise
     */
    public function match(string $method, string $path): bool
    {
        if (count($this->methods) > 0 && !in_array($method, $this->methods)) {
            return false;
        }
        // If the route path contains a regex, match it
        if (false !== strpos($this->path, '(') && false !== strpos($this->path, ')')) {
            $routePath = ltrim($this->path, '/');
            if (1 === preg_match('!'.$routePath.'!', $path, $matches)) {
                $this->actionArgs = array_slice($matches, 1);

                return true;
            }

            return false;
        }
        $path = explode('/', ltrim($path, '/'));
        $routePath = explode('/', trim($this->path, '/'));
        if (count($routePath) !== count($path)) {
            return false;
        }
        foreach ($routePath as $routePartID => &$routePart) {
            if ($routePart === $path[$routePartID]) {
                continue;
            }
            if (!(('{' === substr($routePart, 0, 1) && '}' === substr($routePart, -1))
                || '<' === substr($routePart, 0, 1) && '>' === substr($routePart, -1))) {
                return false;
            }
            if (false !== strpos($routePart, ':')) {
                [$routeType, $actionArgName] = explode(':', substr($routePart, 1, -1));
            } else {
                $actionArgName = substr($routePart, 1, -1);
                if (isset($this->callableParameters[$actionArgName])
                    && $this->callableParameters[$actionArgName]->hasType()) {
                    // @phpstan-ignore method.notFound
                    $routeType = $this->callableParameters[$actionArgName]->getType()->getName();
                } else {
                    $routeType = 'mixed';
                }
            }
            if (!isset($this->callableParameters[$actionArgName])) {
                return false;
            }
            if (('int' === $routeType || 'integer' === $routeType
                || 'float' === $routeType || 'double' === $routeType)
                && !is_numeric($path[$routePartID])) {
                return false;
            }
            if ('bool' === $routeType || 'boolean' === $routeType) {
                $path[$routePartID] = Boolean::from($path[$routePartID]);
            } elseif ('array' === $routeType) {
                $path[$routePartID] = explode(',', $path[$routePartID]);
            } elseif ('json' === $routeType) {
                $path[$routePartID] = json_decode($path[$routePartID], true);
            } elseif ('mixed' !== $routeType) {
                settype($path[$routePartID], $routeType);
            }
            $this->actionArgs[$actionArgName] = $path[$routePartID];
        }

        return true;
    }

    public function getControllerClass(): string
    {
        return is_array($this->callable) ? $this->callable[0] : '';
    }

    public function getControllerName(): string
    {
        return strtolower(basename(str_replace('\\', '/', $this->getControllerClass())));
    }

    /**
     * Retrieves the controller instance based on the callable property.
     *
     * This method checks if the callable property is a Closure or an array.
     * If it is a Closure, it returns a new Closure instance.
     * If it is an array, it extracts the controller class name, verifies its existence,
     * and returns a new instance of the controller class.
     *
     * @return ?Controller the controller instance or null if the callable is not a Closure or an array
     *
     * @throws Router\Exception\ControllerNotFound if the controller class does not exist
     */
    public function getController(): ?Controller
    {
        if ($this->callable instanceof \Closure) {
            return new Closure($this->callable);
        }
        if ($this->callable instanceof \ReflectionMethod) {
            $controllerReflection = $this->callable->getDeclaringClass();
            if (!$controllerReflection->isSubclassOf('\Hazaar\Controller')) {
                throw new Router\Exception\ControllerNotFound($controllerReflection->getName(), $this->path ?? '/');
            }

            return $controllerReflection->newInstance();
        }
        if (is_array($this->callable)) {
            $controllerClass = $this->callable[0];
            $parts = explode('\\', $this->callable[0]);
            $controllerClassName = strtolower(end($parts));
            if (!class_exists($controllerClass)) {
                throw new Router\Exception\ControllerNotFound($controllerClass, $this->path ?? '/');
            }

            return new $controllerClass($controllerClassName);
        }

        return null;
    }

    /**
     * Retrieves the action to be executed.
     *
     * This method checks if the action is defined in the callable array. If it is,
     * it returns that action. Otherwise, it falls back to the default action
     * specified in the router configuration.
     *
     * @return string the action to be executed
     */
    public function getAction(): string
    {
        if ($this->callable instanceof \ReflectionMethod) {
            return $this->callable->getName();
        }

        return isset($this->callable[1]) ? $this->callable[1] : $this->router->config['action'] ?? 'index';
    }

    /**
     * Retrieve the action arguments.
     *
     * This method returns an array of arguments that are passed to the action.
     *
     * @return array<mixed> the action arguments
     */
    public function getActionArgs(): array
    {
        return $this->actionArgs;
    }

    /**
     * Retrieves the response type of the route.
     */
    public function getResponseType(): int
    {
        return $this->responseType;
    }

    /**
     * Prefixes the path of the route with the given path.
     *
     * @param string $path the path to be prefixed
     */
    public function prefixPath(string $path): void
    {
        $this->path = '/'.ltrim($path, '/').$this->path;
    }

    /**
     * Adds middleware(s) to the route.
     *
     * Accepts a string of middleware names separated by colons and merges them into the existing middleware array.
     *
     * @param string $middleware Middleware name(s), separated by colons (e.g., 'auth:logging').
     *
     * @return self returns the current instance for method chaining
     */
    public function middleware(string $middleware, mixed ...$args): self
    {
        $this->middleware[] = new Handler($middleware, $args);

        return $this;
    }

    /**
     * Retrieves the list of middleware associated with the route.
     *
     * @return array<Handler> an array containing middleware instances or definitions
     */
    public function getMiddlewareHandlers(): array
    {
        return $this->middleware;
    }
}
