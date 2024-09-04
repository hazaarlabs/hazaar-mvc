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
use Hazaar\Application\Router\Exception\ControllerNotFound;
use Hazaar\Application\Router\Exception\NoAction;
use Hazaar\Application\Router\Exception\RouteNotFound;
use Hazaar\Application\Router\Loader;
use Hazaar\Cache;
use Hazaar\Controller;
use Hazaar\Controller\Error;
use Hazaar\Controller\Response;
use Hazaar\Map;

class Router implements Interfaces\Router
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

    /**
     * @var array<string,array<string,mixed>>
     */
    public array $routes = [];
    protected Map $config;

    protected ?string $controller = null;
    protected ?string $action = null;

    /**
     * @var array<string>
     */
    protected array $actionArgs = [];
    protected bool $namedActionArgs = false;
    private static ?self $instance = null;

    private ?Controller $__controller = null;

    /**
     * @var array<array<bool|int>>
     */
    private array $__cachedActions = [];

    /**
     * @var array<Response>
     */
    private array $__cachedResponses = [];

    private ?Cache $__responseCache = null;
    private Loader $loader;

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
        $this->loader = new $loaderClass($this->config);
    }

    /**
     * Initializes the Router object and evaluates the request.
     *
     * @param Request $request the request object
     *
     * @throws RouteNotFound
     * @throws NoAction
     */
    final public function __initialise(Request $request): void
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
        $this->loader->loadRoutes($request);
        if (!$this->evaluateRequest($request)) {
            throw new RouteNotFound($request->getPath());
        }
        if (null === $this->controller) {
            throw new RouteNotFound($request->getPath());
        }
    }

    public function __run(Request $request): Response
    {
        if ($response = $this->__getCachedResponse()) {
            return $response;
        }
        $controllerClass = '\\' === substr($this->controller, 0, 1)
            ? $this->controller : '\Application\Controllers\\'.ucfirst($this->controller);
        if (!class_exists($controllerClass)) {
            throw new ControllerNotFound($controllerClass, $request->getPath());
        }
        $this->__controller = new $controllerClass($this, $this->controller);
        // Initialise the controller with the current request
        if ($response = $this->__controller->__initialize($request)) {
            return $response;
        }
        // Execute the controller action
        $response = $this->__controller->__runAction($this->action, $this->actionArgs, $this->namedActionArgs);
        if (false === $response) {
            $response = $this->__controller->__run();
            if (false === $response) {
                throw new NoAction($this->controller);
            }
        }
        $this->__cacheResponse($response);

        return $response;
    }

    final public function __shutdown(Response $response): void
    {
        if (null !== $this->__controller) {
            $this->__controller->__shutdown($response);
        }
        if ($this->__responseCache && count($this->__cachedResponses) > 0) {
            foreach ($this->__cachedResponses as $cacheItem) {
                $this->__responseCache->set($cacheItem[0], $cacheItem[1], $cacheItem[2]);
            }
        }
    }

    private function __getCacheKey(string $controller, string $action, ?string &$cacheName = null): false|string
    {
        $cacheName = $this->controller.'::'.$this->action;
        if (!array_key_exists($cacheName, $this->__cachedActions)) {
            return false;
        }
        $cacheKey = $cacheName.'('.serialize($this->actionArgs).')';
        if (true === $this->__cachedActions[$cacheName]['private'] && ($sid = session_id())) {
            $cacheKey .= '::'.$sid;
        }

        return $cacheKey;
    }

    /**
     * Cache a response to the current action invocation.
     *
     * @param Response $response The response to cache
     */
    private function __cacheResponse(Response $response): bool
    {
        if (null === $this->__responseCache) {
            return false;
        }
        $cacheKey = $this->__getCacheKey($this->controller, $this->action, $cacheName);
        $this->__cachedResponses[] = [$cacheKey, $response, $this->__cachedActions[$cacheName]['timeout']];

        return true;
    }

    private function __getCachedResponse(): false|Response
    {
        if (null === $this->__responseCache) {
            return false;
        }
        $cacheKey = $this->__getCacheKey($this->controller, $this->action);
        if ($response = $this->__responseCache->get($cacheKey)) {
            return $response;
        }

        return false;
    }

    public function evaluateRequest(Request $request): bool
    {
        dump($this->routes);

        if (!$request instanceof Request\HTTP) {
            throw new Router\Exception\NotSupported();
        }
        $method = $request->method();
        if (!array_key_exists($method, $this->routes)) {
            return false;
        }

        $path = explode('/', $request->getPath());
        $routes = $this->routes[$method];
        $match = false;
        foreach ($routes as $route => $callback) {
            $route = explode('/', trim($route, '/'));
            if (count($route) !== count($path)) {
                continue;
            }
            $args = [];
            foreach ($route as $i => &$part) {
                if ($part === $path[$i]) {
                    continue;
                }
                if ('{' === substr($part, 0, 1) && '}' === substr($part, -1)) {
                    $args[] = $path[$i];

                    continue;
                }

                continue 2;
            }
            if ('Application\Controllers\\' === substr($callback[0], 0, 24)) {
                $this->controller = substr($callback[0], 24);
            } else {
                $this->controller = '\\'.$callback[0];
            }
            if (isset($callback[1])) {
                $this->action = $callback[1];
            }
            $this->actionArgs = $args;
            $match = true;

            break;
        }

        return $match;
    }

    public function getControllerName(): ?string
    {
        return $this->controller;
    }

    public function getActionName(): ?string
    {
        return $this->action;
    }

    public function getActionArgs(): array
    {
        return $this->actionArgs;
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

    public function cacheAction(string $controllerName, string $actionName, int $timeout = 60, bool $private = false): bool
    {
        if (null === $this->__responseCache) {
            $this->__responseCache = new Cache('apc');
        }
        $this->__getCacheKey($controllerName, $actionName, $cacheName);
        $this->__cachedActions[$cacheName] = [
            'timeout' => $timeout,
            'private' => $private,
        ];

        return true;
    }

    public static function get(string $path, mixed $callback): void
    {
        self::match(['GET'], $path, $callback);
    }

    public static function post(string $path, mixed $callback): void
    {
        self::match(['POST'], $path, $callback);
    }

    public static function put(string $path, mixed $callback): void
    {
        self::match(['PUT'], $path, $callback);
    }

    public static function delete(string $path, mixed $callback): void
    {
        self::match(['DELETE'], $path, $callback);
    }

    public static function patch(string $path, mixed $callback): void
    {
        self::match(['PATCH'], $path, $callback);
    }

    public static function options(string $path, mixed $callback): void
    {
        self::match(['OPTIONS'], $path, $callback);
    }

    public static function any(string $path, mixed $callback): void
    {
        self::match(['ANY'], $path, $callback);
    }

    public static function default(mixed $callback): void
    {
        if (!self::$instance) {
            return;
        }
        $route = new Route($callback);
        self::$instance->routes['DEFAULT'] = $route;
    }

    /**
     * @param array<string>        $methods
     * @param array{string,string} $callback
     */
    public static function match(array $methods, string $path, mixed $callback): void
    {
        if (!(
            self::$instance
            && (
                is_callable($callback)
                || (is_array($callback) && class_exists($callback[0]))
            )
        )) {
            return;
        }
        $route = new Route($methods, $path, $callback);
        foreach ($methods as $method) {
            self::$instance->routes[$method][$path] = $route;
        }
    }
}
