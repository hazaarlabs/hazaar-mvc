<?php

declare(strict_types=1);

/**
 * @file        Controller/Basic.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Cache\Adapter;
use Hazaar\Controller;
use Hazaar\HTTP\Link;
use Hazaar\Middleware\MiddlewareDispatcher;
use Hazaar\View;

/**
 * @brief       Basic controller class
 *
 * @detail      This controller is a basic controller for directly handling requests.  Developers can extend this class
 *              to create their own flexible controllers for use in modern AJAX enabled websites that don't require
 *              HTML views.
 *
 *              How it works is a request is passed to the controller and the controller is responsible for processing
 *              it, creating a new response object and return that object back to the application for processing.
 *
 *              This controller type is typically used for handling AJAX requests as responses to these requests do not
 *              require rendering any views.  This allows AJAX requests to be processed quickly without the overhead of
 *              rendering a view that will never be displayed.
 */
abstract class Basic extends Controller
{
    protected string $name = 'basic';
    protected bool $stream = false;

    /**
     * @var array<array<bool|int>>
     */
    private array $cachedActions = [];

    /**
     * @var array<Response>
     */
    private array $cachedResponses = [];
    private ?Adapter $responseCache = null;

    /**
     * @var array<Middleware>
     */
    private array $middleware = [];

    public function initialize(Request $request): void
    {
        parent::initialize($request);
        $this->init();
    }

    /**
     * Run the controller action.
     *
     * This is the main entry point for the controller when there is a route to run.  It is called by the application
     * to run a route and execute the controller action.  The action name and arguments are taken from the route.
     *
     * @param null|Route $route the route to run, or null if no route is provided
     */
    public function run(?Route $route = null): Response
    {
        $finalHandler = function () use ($route): Response {
            if ($response = $this->getCachedResponse($route)) {
                return $response;
            }
            // Execute the controller action
            $response = $this->runAction($route->getAction(), $route->getActionArgs());
            $this->cacheResponse($route, $response);

            return $response;
        };
        if (count($this->middleware) > 0) {
            // If we have middleware, we need to run it first
            $dispatcher = new MiddlewareDispatcher();
            $appliedMiddleware = [];
            foreach ($this->middleware as $middleware) {
                if ($middleware->match($route->getAction())) {
                    $appliedMiddleware[] = $middleware->name;
                }
            }
            $dispatcher->addFromArray($appliedMiddleware);

            return $dispatcher->handle($this->request, $finalHandler);
        }

        return $finalHandler();
    }

    final public function shutdown(): void
    {
        if (!($this->responseCache && count($this->cachedResponses) > 0)) {
            return;
        }
        foreach ($this->cachedResponses as $cacheItem) {
            $this->responseCache->set($cacheItem[0], $cacheItem[1], $cacheItem[2]);
        }
    }

    /**
     * Run an action method on a controller.
     *
     * This is the main controller action decision code and is where the controller will decide what to
     * actually execute and whether to cache the response on not.
     *
     * @param array<mixed> $actionArgs The arguments to pass to the action
     *
     * @throws Exception\ActionNotFound
     * @throws Exception\ActionNotPublic
     * @throws Exception\ResponseInvalid
     */
    public function runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): Response
    {
        /*
         * Check if we have routed to the default controller, or the action method does not
         * exist and if not check for the __default() method.  Otherwise we have nothing
         * to execute so throw a nasty exception.
         */
        if (!method_exists($this, $actionName)) {
            if (method_exists($this, '__default')) {
                if (true === $namedActionArgs) {
                    throw new \Exception('Named action arguments are not supported for default actions.');
                }
                array_unshift($actionArgs, $actionName);
                array_unshift($actionArgs, $this->name);
                $actionName = '__default';
            } else {
                throw new Exception\ActionNotFound(get_class($this), $actionName);
            }
        }
        $method = new \ReflectionMethod($this, $actionName);
        if (!$method->isPublic()) {
            throw new Exception\ActionNotPublic(get_class($this), $actionName);
        }
        if (true === $namedActionArgs) {
            $params = [];
            foreach ($method->getParameters() as $p) {
                $key = $p->getName();
                $value = null;
                if (array_key_exists($key, $actionArgs)) {
                    $value = $actionArgs[$key];
                } elseif ($p->isDefaultValueAvailable()) {
                    $value = $p->getDefaultValue();
                } else {
                    throw new \Exception("Missing value for parameter '{$key}'.", 400);
                }
                $params[$p->getPosition()] = $value;
            }
        } else {
            $params = $actionArgs;
        }
        $response = $method->invokeArgs($this, $params);
        if (null === $response) {
            throw new Exception\ResponseInvalid(get_class($this), $actionName);
        }
        if ($this->stream) {
            return new Response\Stream($response);
        }
        if (is_array($response)) {
            $response = new Response\JSON($response);
        } elseif (is_string($response) || is_numeric($response)) {
            $response = new Response\Text($response);
        } elseif ($response instanceof View) {
            $response = new Response\View($response);
        } elseif (!$response instanceof Response) {
            $response = new Response\HTTP\NoContent();
        }

        return $response;
    }

    /**
     * Sends a stream response to the client.
     *
     * This method sends a stream response to the client, allowing the client to download
     * the response as a file. It sets the necessary headers for the response and flushes
     * the output buffer to ensure the response is sent immediately.
     *
     * @param array<mixed>|string $value The value to be streamed. If an array is provided, it will be
     *                                   converted to a JSON string before streaming.
     *
     * @return bool returns true if the stream response was successfully sent, false otherwise
     */
    public function stream(array|string $value): bool
    {
        if (!headers_sent()) {
            if (count(ob_get_status()) > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/octet-stream;charset=ISO-8859-1');
            header('Content-Encoding: none');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('X-Accel-Buffering: no');
            header('X-Response-Type: stream');
            flush();
            $this->stream = true;
            ob_implicit_flush();
        }
        $type = 's';
        if (is_array($value)) {
            $value = json_encode($value);
            $type = 'a';
        }
        echo dechex(strlen($value))."\0".$type.$value;

        return true;
    }

    /**
     * Send early hints to the client.
     *
     * This method sends early hints to the client using the HTTP/2 or HTTP/3 protocol.  It is used to inform the client
     * about resources that should be preloaded or prefetched.  The `Link` class is used to create the links.
     *
     * @param array<Link> $links an array of Link objects to send as early hints
     */
    public function sendEarlyHints(array $links): bool
    {
        if (!function_exists('headers_send')) {
            return false;
        }
        foreach ($links as $link) {
            header((string) $link, false);
        }
        headers_send(103);

        return true;
    }

    public function cacheAction(string $actionName, int $timeout = 60, bool $private = false): bool
    {
        if (null === $this->responseCache) {
            $this->responseCache = new Adapter();
        }
        $this->getCacheKey($this->name, $actionName, [], $cacheName);
        $this->cachedActions[$cacheName] = [
            'timeout' => $timeout,
            'private' => $private,
        ];

        return true;
    }

    /**
     * Creates and returns a new Middleware instance with the specified name.
     *
     * @param string $name the name of the middleware to instantiate
     *
     * @return Middleware the newly created Middleware instance
     */
    protected function middleware(string $name): Middleware
    {
        return $this->middleware[] = new Middleware($name);
    }

    protected function init(): void {}

    /**
     * Get the cache key for the current action.
     *
     * @param string       $controller the controller name
     * @param string       $action     the action name
     * @param array<mixed> $actionArgs the action arguments
     * @param null|string  $cacheName  the cache name
     *
     * @param-out string $cacheName
     */
    private function getCacheKey(
        string $controller,
        string $action,
        ?array $actionArgs = null,
        ?string &$cacheName = null
    ): false|string {
        $cacheName = $controller.'::'.$action;
        if (!array_key_exists($cacheName, $this->cachedActions)) {
            return false;
        }

        return $cacheName.'('.serialize($actionArgs).')';
    }

    // Cache a response to the current action invocation.
    private function cacheResponse(Route $route, Response $response): bool
    {
        if (null === $this->responseCache) {
            return false;
        }
        $cacheKey = $this->getCacheKey($this->name, $route->getAction(), $route->getActionArgs(), $cacheName);
        if (false !== $cacheKey) {
            $this->cachedResponses[] = [$cacheKey, $response, $this->cachedActions[$cacheName]['timeout']];
        }

        return true;
    }

    private function getCachedResponse(Route $route): ?Response
    {
        if (null === $this->responseCache) {
            return null;
        }
        $cacheKey = $this->getCacheKey($this->name, $route->getAction(), $route->getActionArgs());
        if (false !== $cacheKey && ($response = $this->responseCache->get($cacheKey))) {
            return $response;
        }

        return null;
    }
}
