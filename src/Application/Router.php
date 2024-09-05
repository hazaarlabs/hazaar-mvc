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
use Hazaar\Cache;
use Hazaar\Controller\Error;
use Hazaar\Controller\Response;
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

    protected Map $config;

    protected ?string $controller = null;
    protected ?string $action = null;

    /**
     * @var array<string>
     */
    protected array $actionArgs = [];
    protected bool $namedActionArgs = false;
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
        if ($controller = $this->config->get('controller')) {
            $this->controller = ucfirst($controller);
        }
        $this->action = $this->config->get('action');
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
                $this->controller = self::$internal[$route];
                $this->action = substr($path, $offset + 1);
                $request->setPath(substr($path, $offset + 1));

                return;
            }
        }
        self::$instance = $this; // Set the instance to this object so that static methods will use this instance
        $this->routeLoader->exec($request);
        if (null === $this->route) {
            $this->route = $this->evaluateRequest($request);
            if (null === $this->route) {
                throw new RouteNotFound($request->getPath());
            }
        }
    }

    public function addRoute(Route $route): void
    {
        $route->setRouter($this);
        $this->routes[] = $route;
    }

    public function setRoute(Route $route): void
    {
        $this->route = $route;
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function getControllerName(): ?string
    {
        return $this->controller;
    }

    public function getActionName(): ?string
    {
        return $this->action;
    }

    public function getDefaultControllerName(): string
    {
        return $this->config->get('controller');
    }

    public function getDefaultActionName(): string
    {
        return $this->config->get('action');
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
            $controller = new Error($this);
        }

        return $controller;
    }

    public static function get(string $path, mixed $callable): void
    {
        self::match(['GET'], $path, $callable);
    }

    public static function post(string $path, mixed $callable): void
    {
        self::match(['POST'], $path, $callable);
    }

    public static function put(string $path, mixed $callable): void
    {
        self::match(['PUT'], $path, $callable);
    }

    public static function delete(string $path, mixed $callable): void
    {
        self::match(['DELETE'], $path, $callable);
    }

    public static function patch(string $path, mixed $callable): void
    {
        self::match(['PATCH'], $path, $callable);
    }

    public static function options(string $path, mixed $callable): void
    {
        self::match(['OPTIONS'], $path, $callable);
    }

    public static function any(string $path, mixed $callable): void
    {
        self::match(['ANY'], $path, $callable);
    }

    public static function set(mixed $callable): void
    {
        if (!self::$instance) {
            return;
        }
        self::$instance->setRoute(new Route($callable));
    }

    /**
     * @param array<string>        $methods
     * @param array{string,string} $callable
     */
    public static function match(array $methods, string $path, mixed $callable): void
    {
        if (!(
            self::$instance
            && (
                is_callable($callable)
                || (is_array($callable) && class_exists($callable[0]))
            )
        )) {
            return;
        }
        self::$instance->addRoute(new Route($callable, $path, $methods));
    }

    private function evaluateRequest(Request $request): ?Route
    {
        if (!$request instanceof Request\HTTP) {
            throw new Router\Exception\ProtocolNotSupported();
        }
        $method = $request->method();
        foreach ($this->routes as $route) {
            if ($route->match($method, $request->getPath())) {
                return $this->route = $route;
            }
        }

        return null;
    }
}
