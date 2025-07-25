<?php

namespace Hazaar\Middleware;

use Hazaar\Application\Request;
use Hazaar\Controller\Response;
use Hazaar\Middleware\Interface\Middleware;

class Handler
{
    /**
     * Name of the middleware.
     */
    public string $name;

    /**
     * Arguments passed to the middleware.
     *
     * @var array<mixed>
     */
    public array $args = [];

    /**
     * Array of methods this middleware applies to.
     *
     * @var array<string>
     */
    private array $methods = [];

    /**
     * Array of methods this middleware does not apply to.
     *
     * @var array<string>
     */
    private array $exceptMethods = [];

    /**
     * Static array to hold middleware instances.
     *
     * This is used to store instances of middleware classes to avoid
     * creating multiple instances of the same middleware class.
     *
     * @var array<string,Middleware>
     */
    private static array $middlewareInstances = [];

    /**
     * Constructor to initialize the middleware handler.
     *
     * @param string       $middleware Name of the middleware
     * @param array<mixed> $args       Arguments for the middleware
     */
    public function __construct(string $middleware, array $args = [])
    {
        if (array_key_exists($middleware, Dispatcher::$aliases)) {
            $middleware = Dispatcher::$aliases[$middleware];
        }
        if (!class_exists($middleware)) {
            throw new \InvalidArgumentException("Class {$middleware} does not exist.");
        }
        if (!is_subclass_of($middleware, Middleware::class)) {
            throw new \InvalidArgumentException("Class {$middleware} does not implement Middleware interface.");
        }
        // Initialize middleware with the given name
        $this->name = $middleware;
        $this->args = $args;
    }

    /**
     * Sets the middleware instance for the current handler.
     *
     * Stores the provided Middleware instance in a static array, indexed by the handler's name.
     *
     * @param Middleware $instance the middleware instance to associate with this handler
     */
    public function setInstance(Middleware $instance): void
    {
        // Store the instance in the static array
        self::$middlewareInstances[$this->name] = $instance;
    }

    /**
     * Executes the middleware by either reusing an existing instance or creating a new one,
     * then calls its 'handle' method with the provided request, next callable, and any additional arguments.
     *
     * @param Request  $request the current HTTP request object
     * @param callable $next    the next middleware or handler to be executed
     *
     * @return Response the HTTP response returned by the middleware
     */
    public function run(Request $request, callable $next): Response
    {
        if (array_key_exists($this->name, self::$middlewareInstances)) {
            // Use the existing instance if it exists
            $middlewareInstance = self::$middlewareInstances[$this->name];
        } else {
            // Create a new instance and store it in the static array
            $middlewareInstance = new $this->name();
            self::$middlewareInstances[$this->name] = $middlewareInstance;
        }
        $middlewareArgs = array_merge([$request, $next], $this->args);

        // Fallback to handle method if run is not defined
        return call_user_func_array([$middlewareInstance, 'handle'], $middlewareArgs);
    }

    /**
     * Specifies that this middleware should only apply to the given HTTP method.
     *
     * @param string $method The HTTP method (e.g., 'GET', 'POST') to which the middleware should be restricted.
     *
     * @return self returns the current instance for method chaining
     */
    public function only(string $method): self
    {
        // Set the methods that this middleware should apply to
        $this->methods[] = $method;

        return $this;
    }

    /**
     * Excludes the specified HTTP method from being processed by this middleware.
     *
     * @param string $method The HTTP method to exclude (e.g., 'GET', 'POST').
     *
     * @return self returns the current instance for method chaining
     */
    public function except(string $method): self
    {
        // Set the methods that this middleware should not apply to
        $this->exceptMethods[] = $method;

        return $this;
    }

    /**
     * Determines if the given action name matches the middleware's method rules.
     *
     * Checks if the provided action name is included in the allowed methods and not in the excepted methods.
     * If no specific methods are set, only checks against the excepted methods.
     *
     * @param string $actionName the name of the action to check
     *
     * @return bool true if the action matches the middleware's rules, false otherwise
     */
    public function match(string $actionName): bool
    {
        if (count($this->methods) > 0) {
            return in_array($actionName, $this->methods) && !in_array($actionName, $this->exceptMethods);
        }

        return !in_array($actionName, $this->exceptMethods);
    }
}
