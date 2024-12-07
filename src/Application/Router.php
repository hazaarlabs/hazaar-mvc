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

class Router
{
    public const RESPONSE_HTML = 1;
    public const RESPONSE_JSON = 2;
    public const RESPONSE_XML = 3;
    public const RESPONSE_TEXT = 4;
    public const RESPONSE_BINARY = 5;

    /**
     * Default configuration.
     *
     * @var array<mixed>
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

    /**
     * @var array< mixed>
     */
    public array $config;
    private static ?self $instance = null;
    private Loader $routeLoader;

    /**
     * @var array<Route>
     */
    private array $routes = [];

    private static int $defaultResponseType = self::RESPONSE_HTML;

    /**
     * Creates a new Router object.
     *
     * @param array<mixed> $config the configuration settings
     */
    final public function __construct(array $config)
    {
        self::$instance = $this;
        $this->config = array_merge(self::$defaultConfig, $config);
        $type = $this->config['type'] ?? 'file';
        $loaderClass = '\Hazaar\Application\Router\Loader\\'.ucfirst($type);
        if (!class_exists($loaderClass)) {
            throw new Router\Exception\LoaderNotSupported($type);
        }
        $this->routeLoader = new $loaderClass($this->config);
    }

    /**
     * Initializes the Router object and evaluates the request.
     *
     * @throws RouteNotFound
     */
    final public function initialise(): bool
    {
        self::$instance = $this; // Set the instance to this object so that static methods will use this instance
        if (false === $this->routeLoader->initialise($this)) {
            return false;
        }

        return true;
    }

    /**
     * Evaluates the given request and matches it against the defined routes.
     *
     * @param Request $request the request to evaluate
     *
     * @return null|Route the matched route or null if no route matches
     */
    public function evaluateRequest(Request $request): ?Route
    {
        $matchedRoute = null;
        $path = $request->getPath();
        // Search for internal controllers
        if ($offset = strpos($path, '/', 1)) {
            $route = substr($path, 0, $offset);
            if (array_key_exists($route, self::$internal)) {
                $controller = self::$internal[$route];
                $action = substr($path, $offset + 1);

                return new Route([$controller, $action], substr($path, $offset + 1));
            }
        }
        $matchedRoute = $this->routeLoader->evaluateRequest($request);
        if ($matchedRoute instanceof Route) {
            return $matchedRoute;
        }
        $method = $request->getMethod();
        foreach ($this->routes as $route) {
            if ($route->match($method, $path)) {
                return $matchedRoute = $route;
            }
        }
        // If no route is found, and the path is '/', use the default controller
        if (!('/' === $request->getPath() && ($controller = $this->config['controller']))) {
            return null;
        }

        $controllerClass = '\\' === substr($controller, 0, 1)
            ? $controller
            : 'Application\Controller\\'.ucfirst($controller);

        return new Route([$controllerClass, $this->config['action']]);
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

    public function getErrorController(): Error
    {
        $controller = null;
        if (isset($this->config['errorController'])
            && ($errorController = $this->config['errorController'])) {
            $controllerClass = '\Application\Controllers\\'.ucfirst($errorController);
            if (class_exists($controllerClass) && is_subclass_of($controllerClass, Error::class)) {
                $controller = new $controllerClass($this, $errorController);
            }
        }
        if (null === $controller) {
            $controller = new Error('error');
        }

        return $controller;
    }

    /**
     * Registers a route that responds to HTTP GET requests.
     *
     * @param string $path         the URL path for the route
     * @param mixed  $callable     the callback or controller action to handle the request
     * @param int    $responseType The expected response type for this route
     */
    public static function get(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['GET'], $path, $callable, $responseType);
    }

    /**
     * Registers a route that responds to HTTP POST requests.
     *
     * @param string $path         the URL path for the route
     * @param mixed  $callable     the callback or controller method to handle the request
     * @param int    $responseType The expected response type for this route
     */
    public static function post(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['POST'], $path, $callable, $responseType);
    }

    /**
     * Registers a route that responds to HTTP PUT requests.
     *
     * @param string $path         the URI path that the route will respond to
     * @param mixed  $callable     the handler for the route, which can be a callable or other valid route handler
     * @param int    $responseType The expected response type for this route
     */
    public static function put(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['PUT'], $path, $callable, $responseType);
    }

    /**
     * Registers a route that responds to HTTP DELETE requests.
     *
     * @param string $path         the URL path that the route should match
     * @param mixed  $callable     the callback or controller action to be executed when the route is matched
     * @param int    $responseType The expected response type for this route
     */
    public static function delete(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['DELETE'], $path, $callable, $responseType);
    }

    /**
     * Registers a route that responds to HTTP PATCH requests.
     *
     * @param string $path         the URI path that the route will match
     * @param mixed  $callable     the callback or controller action to be executed when the route is matched
     * @param int    $responseType The expected response type for this route
     */
    public static function patch(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['PATCH'], $path, $callable, $responseType);
    }

    /**
     * Registers a route that responds to HTTP OPTIONS requests.
     *
     * @param string $path         the URL path to match
     * @param mixed  $callable     the callback or controller action to handle the request
     * @param int    $responseType The expected response type for this route
     */
    public static function options(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['OPTIONS'], $path, $callable, $responseType);
    }

    /**
     * Registers a route that responds to any HTTP method.
     *
     * @param string $path         the path pattern to match
     * @param mixed  $callable     the callback to execute when the route is matched
     * @param int    $responseType The expected response type for this route
     */
    public static function any(string $path, mixed $callable, ?int $responseType = null): void
    {
        self::match(['ANY'], $path, $callable, $responseType);
    }

    /**
     * Matches a route with the given HTTP methods, path, and callable.
     *
     * @param null|array<string>|string $methods      The HTTP methods to match (e.g., ['GET', 'POST']).
     * @param string                    $path         The path to match (e.g., '/user/{id}').
     * @param mixed                     $callable     The callable to execute when the route is matched.
     *                                                It can be a string in the format 'Class::method',
     *                                                an array with the class and method, or a Closure.
     * @param int                       $responseType The expected response type for this route
     */
    public static function match(null|array|string $methods, string $path, mixed $callable, ?int $responseType = null): void
    {
        if (!self::$instance) {
            return;
        }
        if (is_string($callable)) {
            $callable = explode('::', $callable);
        }
        self::$instance->addRoute(new Route($callable, $path, $methods, $responseType ?? self::$defaultResponseType));
    }

    /**
     * Sets the default response type for the application.
     *
     * @param int $type the response type to be set
     */
    public static function setResponseType(int $type): void
    {
        self::$defaultResponseType = $type;
    }

    /**
     * Sets the response type for the application to HTML.
     */
    public static function html(): void
    {
        self::$instance->setResponseType(self::RESPONSE_HTML);
    }

    /**
     * Sets the response type for the application to JSON.
     */
    public static function json(): void
    {
        self::$instance->setResponseType(self::RESPONSE_JSON);
    }

    /**
     * Sets the response type for the application to XML.
     */
    public static function xml(): void
    {
        self::$instance->setResponseType(self::RESPONSE_XML);
    }

    /**
     * Sets the response type for the application to text.
     */
    public static function text(): void
    {
        self::$instance->setResponseType(self::RESPONSE_TEXT);
    }

    /**
     * Sets the response type for the application to binary.
     */
    public static function binary(): void
    {
        self::$instance->setResponseType(self::RESPONSE_BINARY);
    }
}
