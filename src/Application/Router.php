<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Application/Router.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Application;

use Hazaar\Application;
use Hazaar\Application\Router\Exception\RouteNotFound;
use Hazaar\Application\Router\Loader;
use Hazaar\Controller\Error;
use Hazaar\Map;

class Router
{
    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    public static array $defaultConfig = [
        'controller' => 'index',
        'action' => 'index',
        'args' => [],
    ];

    /**
     * Internal controllers.
     *
     * @var array<string, string>
     */
    public static array $internal = [
        'hazaar' => '\Hazaar\Controller\Internal',
    ];

    public Application $application;
    public Map $config;
    private static ?self $instance = null;
    private Loader $routeLoader;

    /**
     * @var array<Route>
     */
    private array $routes = [];
    private ?Route $route = null;

    final public function __construct(Application $application, Map $config)
    {
        self::$instance = $this;
        $this->application = $application;
        $this->config = $config;
        $this->config->enhance(self::$defaultConfig);
        $type = $this->config->get('type', 'file');
        $loaderClass = '\Hazaar\Application\Router\Loader\\'.ucfirst($type);
        if (!class_exists($loaderClass)) {
            throw new Router\Exception\LoaderNotSupported($type);
        }
        $this->routeLoader = new $loaderClass($this->config);
    }

    /**
     * Initializes the Router object and evaluates the request.
     *
     * @param Request $request the request object
     *
     * @throws RouteNotFound
     */
    final public function initialise(Request $request): void
    {
        // Search for internal controllers
        if (($path = $request->getPath())
            && ($offset = strpos($path, '/', 1))) {
            $route = substr($path, 0, $offset);
            if (array_key_exists($route, self::$internal)) {
                $controller = self::$internal[$route];
                $action = substr($path, $offset + 1);
                $request->setPath(substr($path, $offset + 1));
                $this->setRoute(new Route([$controller, $action]));

                return;
            }
        }
        self::$instance = $this; // Set the instance to this object so that static methods will use this instance
        if (false === $this->routeLoader->exec($request)) {
            throw new RouteNotFound($request->getPath());
        }
        // If the loader has not already set a route, evaluate the request
        if (null === $this->route) {
            $this->route = $this->evaluateRequest($request);
        }
        if (null === $this->route && !$request->getPath() && ($controller = $this->config->get('controller'))) {
            $controllerClass = '\\' === substr($controller, 0, 1)
                ? $controller
                : 'Application\Controllers\\'.ucfirst($controller);
            $this->setRoute(new Route([$controllerClass, $this->config->get('action')]));
        }
        if (null === $this->route) {
            throw new RouteNotFound($request->getPath());
        }
    }

    public static function reset(): void
    {
        if (self::$instance) {
            self::$instance->route = null;
        }
    }

    /**
     * Adds a route to the router.
     *
     * This method sets the router for the given route and then adds the route
     * to the list of routes managed by this router.
     *
     * @param Route $route the route to be added
     */
    public function addRoute(Route $route): void
    {
        $route->setRouter($this);
        $this->routes[] = $route;
    }

    /**
     * Sets the current route for the router.
     *
     * This method assigns the provided route to the router and sets the router
     * instance within the route.
     *
     * @param Route $route the route to be set
     */
    public function setRoute(Route $route): void
    {
        $route->setRouter($this);
        $this->route = $route;
    }

    /**
     * Retrieves the current route.
     *
     * @return null|Route the current route, or null if no route is set
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function getErrorController(): Error
    {
        $controller = null;
        if ($errorController = $this->config->get('errorController')) {
            $controllerClass = '\Application\Controllers\\'.ucfirst($errorController);
            if (class_exists($controllerClass) && is_subclass_of($controllerClass, Error::class)) {
                $controller = new $controllerClass($this, $errorController);
            }
        }
        if (null === $controller) {
            $controller = new Error($this->application, 'error');
        }

        return $controller;
    }

    /**
     * Registers a route that responds to HTTP GET requests.
     *
     * @param string $path     the URL path for the route
     * @param mixed  $callable the callback or controller action to handle the request
     */
    public static function get(string $path, mixed $callable): void
    {
        self::match(['GET'], $path, $callable);
    }

    /**
     * Registers a route that responds to HTTP POST requests.
     *
     * @param string $path     the URL path for the route
     * @param mixed  $callable the callback or controller method to handle the request
     */
    public static function post(string $path, mixed $callable): void
    {
        self::match(['POST'], $path, $callable);
    }

    /**
     * Registers a route that responds to HTTP PUT requests.
     *
     * @param string $path     the URI path that the route will respond to
     * @param mixed  $callable the handler for the route, which can be a callable or other valid route handler
     */
    public static function put(string $path, mixed $callable): void
    {
        self::match(['PUT'], $path, $callable);
    }

    /**
     * Registers a route that responds to HTTP DELETE requests.
     *
     * @param string $path     the URL path that the route should match
     * @param mixed  $callable the callback or controller action to be executed when the route is matched
     */
    public static function delete(string $path, mixed $callable): void
    {
        self::match(['DELETE'], $path, $callable);
    }

    /**
     * Registers a route that responds to HTTP PATCH requests.
     *
     * @param string $path     the URI path that the route will match
     * @param mixed  $callable the callback or controller action to be executed when the route is matched
     */
    public static function patch(string $path, mixed $callable): void
    {
        self::match(['PATCH'], $path, $callable);
    }

    /**
     * Registers a route that responds to HTTP OPTIONS requests.
     *
     * @param string $path     the URL path to match
     * @param mixed  $callable the callback or controller action to handle the request
     */
    public static function options(string $path, mixed $callable): void
    {
        self::match(['OPTIONS'], $path, $callable);
    }

    /**
     * Registers a route that responds to any HTTP method.
     *
     * @param string $path     the path pattern to match
     * @param mixed  $callable the callback to execute when the route is matched
     */
    public static function any(string $path, mixed $callable): void
    {
        self::match(['ANY'], $path, $callable);
    }

    /**
     * Sets a new route for the application.
     *
     * This method sets a new route by accepting a callable and creating a new Route instance with it.
     * If the Router instance is not initialized, the method will return without setting the route.
     *
     * @param mixed $callable the callable to be used for the new route
     */
    public static function set(mixed $callable, ?string $path = null, bool $namedActionArgs = false): void
    {
        if (!self::$instance) {
            return;
        }
        self::$instance->setRoute(new Route($callable, $path, [], $namedActionArgs));
    }

    /**
     * Matches a route with the given HTTP methods, path, and callable.
     *
     * @param null|array<string>|string $methods         The HTTP methods to match (e.g., ['GET', 'POST']).
     * @param string                    $path            The path to match (e.g., '/user/{id}').
     * @param mixed                     $callable        The callable to execute when the route is matched.
     *                                                   It can be a string in the format 'Class::method',
     *                                                   an array with the class and method, or a Closure.
     * @param bool                      $namedActionArgs whether to use named action arguments
     */
    public static function match(null|array|string $methods, string $path, mixed $callable, bool $namedActionArgs = false): void
    {
        if (!self::$instance) {
            return;
        }
        if (is_string($callable)) {
            $callable = explode('::', $callable);
        }
        self::$instance->addRoute(new Route($callable, $path, $methods, $namedActionArgs));
    }

    /**
     * Evaluates the given request and matches it against the defined routes.
     *
     * @param Request $request the request to evaluate
     *
     * @return null|Route the matched route or null if no route matches
     */
    private function evaluateRequest(Request $request): ?Route
    {
        $method = $request->getMethod();
        $path = $request->getPath();
        foreach ($this->routes as $route) {
            if ($route->match($method, $path)) {
                return $this->route = $route;
            }
        }

        return null;
    }
}
