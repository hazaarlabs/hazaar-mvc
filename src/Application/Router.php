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
use Hazaar\Cache;
use Hazaar\Controller;
use Hazaar\Controller\Error;
use Hazaar\Controller\Response;
use Hazaar\Map;

abstract class Router implements Interfaces\Router
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

    final public function __construct(Application $application, Config $config)
    {
        $this->application = $application;
        $this->config = $config->get('router', self::$defaultConfig);
        if ($controller = $this->config->get('controller')) {
            $this->controller = ucfirst($controller);
        }
        $this->action = $this->config->get('action');
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
                $request->setPath(substr($path, $offset + 1));

                return;
            }
        }
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
            ? $this->controller : '\\Application\\Controllers\\'.ucfirst($this->controller);
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
        return false;
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
            $controllerClass = '\\Application\\Controllers\\'.ucfirst($errorController);
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
}
