<?php

namespace Hazaar\Application;

use Hazaar\Controller;
use Hazaar\Controller\Closure;

class Route
{
    private Router $router;
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

    /**
     * @param array<string> $methods
     */
    public function __construct(mixed $callable, ?string $path = null, array $methods = [])
    {
        $this->callable = $callable;
        $this->path = $path;
        $this->methods = array_map('strtoupper', $methods);
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
     * Matches the given HTTP method and path against the route's method and path.
     *
     * @param string $method The HTTP method to match (e.g., 'GET', 'POST').
     * @param string $path   the request path to match
     *
     * @return bool returns true if the method and path match the route, false otherwise
     */
    public function match(string $method, string $path): bool
    {
        if (!in_array($method, $this->methods)) {
            return false;
        }
        $path = explode('/', $path);
        $routePath = explode('/', trim($this->path, '/'));
        if (count($routePath) !== count($path)) {
            return false;
        }
        foreach ($routePath as $i => &$part) {
            if ($part === $path[$i]) {
                continue;
            }
            if ('{' === substr($part, 0, 1) && '}' === substr($part, -1)) {
                $this->actionArgs[] = $path[$i];

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
}
