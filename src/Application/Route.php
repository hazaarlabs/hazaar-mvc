<?php

namespace Hazaar\Application;

use Hazaar\Controller;
use Hazaar\Controller\Closure;

class Route
{
    public Router $router;
    private mixed $callable;
    private ?string $path = null;

    /**
     * @var array<string>
     */
    private array $methods = [];

    /**
     * @var array<mixed>
     */
    private array $actionArgs = [];
    private bool $namedActionArgs = false;

    /**
     * @param array<string> $methods
     */
    public function __construct(mixed $callable, ?string $path = null, array $methods = [], bool $namedActionArgs = false)
    {
        $this->callable = $callable;
        $this->path = $path;
        $this->methods = array_map('strtoupper', $methods);
        $this->namedActionArgs = $namedActionArgs;
        if (is_array($this->callable) && isset($this->callable[2]) && is_array($this->callable[2])) {
            $this->actionArgs = $this->callable[2];
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
        $path = explode('/', $path);
        $routePath = explode('/', trim($this->path, '/'));
        if (count($routePath) !== count($path)) {
            return false;
        }
        foreach ($routePath as $i => &$routePart) {
            if ($routePart === $path[$i]) {
                continue;
            }
            if (('{' === substr($routePart, 0, 1) && '}' === substr($routePart, -1))
                || '<' === substr($routePart, 0, 1) && '>' === substr($routePart, -1)) {
                if (false !== strpos($routePart, ':')) {
                    list($type, $key) = explode(':', substr($routePart, 1, -1));
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
                    $this->actionArgs[$key] = $path[$i];
                } else {
                    $this->actionArgs[] = $path[$i];
                }

                continue;
            }

            return false;
        }

        return true;
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
            return new Closure($this->router->application, $this->callable);
        }
        if ($this->callable instanceof \ReflectionMethod) {
            $controllerReflection = $this->callable->getDeclaringClass();
            if (!$controllerReflection->isSubclassOf('\Hazaar\Controller')) {
                throw new Router\Exception\ControllerNotFound($controllerReflection->getName(), $this->path ?? '/');
            }

            return $controllerReflection->newInstance($this->router->application);
        }
        if (is_array($this->callable)) {
            $controllerClass = $this->callable[0];
            $parts = explode('\\', $this->callable[0]);
            $controllerClassName = strtolower(end($parts));
            if (!class_exists($controllerClass)) {
                throw new Router\Exception\ControllerNotFound($controllerClass, $this->path ?? '/');
            }

            return new $controllerClass($this->router->application, $controllerClassName);
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

        return isset($this->callable[1]) ? $this->callable[1] : $this->router->config->get('action');
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
     * Retrieves the named action arguments flag.
     *
     * This method returns the namedActionArgs property, which is used to determine
     * whether the action arguments are named.
     *
     * @return bool the namedActionArgs flag
     */
    public function hasNamedActionArgs(): bool
    {
        return $this->namedActionArgs;
    }
}
