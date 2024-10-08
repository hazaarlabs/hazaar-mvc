<?php

declare(strict_types=1);

/**
 * @file        Controller/Controller.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\URL;
use Hazaar\Controller\Exception\NoAction;
use Hazaar\Controller\Helper;
use Hazaar\Controller\Response;

/**
 * Base Controller class.
 *
 * All controller classes extend this class.  Normally this class would only be extended by the controller classes
 * provided by Hazaar MVC, as how a controller actually behaves and the functionality it provides is actually defined
 * by the controller itself.  This controller does nothing, but will still initialise and run, but will output nothing.
 */
abstract class Controller implements Controller\Interfaces\Controller
{
    protected Application $application;
    protected string $name;
    protected Request $request;
    protected int $statusCode = 0;
    protected string $basePath;    // Optional basePath for controller relative url() calls.

    /**
     * @var array<mixed>
     */
    private array $_helpers = [];

    /**
     * @var array<array<bool|int>>
     */
    private array $cachedActions = [];

    /**
     * @var array<Response>
     */
    private array $cachedResponses = [];

    private ?Cache $responseCache = null;

    /**
     * Base controller constructor.
     *
     * @param string $name The name of the controller.  This is the name used when generating URLs.
     */
    public function __construct(Application $application, ?string $name = null)
    {
        $this->application = $application;
        $this->name = strtolower(null !== $name ? $name : get_class($this));
        $this->addHelper('response');
    }

    /**
     * Convert the controller object into a string.
     */
    public function __toString(): string
    {
        return get_class($this);
    }

    /**
     * Get the specified helper object.
     *
     * @param string $helper the name of the helper
     *
     * @return null|Helper the helper object if found, null otherwise
     */
    public function __get(string $helper): ?Helper
    {
        if (array_key_exists($helper, $this->_helpers)) {
            return $this->_helpers[$helper];
        }

        return null;
    }

    /**
     * Controller initialisation method.
     *
     * This should be called by all extending controllers and is simply responsible for storing the calling request.
     *
     * @param Request $request the application request object
     */
    public function initialize(Request $request): ?Response
    {
        $this->request = $request;

        return null;
    }

    public function run(?Route $route = null): Response
    {
        if ($response = $this->getCachedResponse($route)) {
            return $response;
        }
        // Execute the controller action
        $response = $this->runAction($route->getAction(), $route->getActionArgs(), $route->hasNamedActionArgs());
        if (false === $response) {
            throw new NoAction($this->name);
        }
        $this->cacheResponse($route, $response);

        return $response;
    }

    public function runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response
    {
        return false;
    }

    final public function shutdown(Response $response): void
    {
        if (!($this->responseCache && count($this->cachedResponses) > 0)) {
            return;
        }
        foreach ($this->cachedResponses as $cacheItem) {
            $this->responseCache->set($cacheItem[0], $cacheItem[1], $cacheItem[2]);
        }
    }

    /**
     * Get the name of the controller.
     *
     * @return string the name of the controller
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the default return status code.
     *
     * @param int $code the default status code that will used on responses
     */
    public function setStatus($code = null): void
    {
        $this->statusCode = $code;
    }

    /**
     * Get the status code of the controller.
     *
     * @return int the status code
     */
    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the base path of the controller.
     *
     * @return string the base path of the controller
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set the base path for the controller.
     *
     * @param string $path the base path to set
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = $path;
    }

    /**
     * Initiate a redirect response to the client.
     */
    public function redirect(string|URL $location, bool $saveURI = false): Response
    {
        return $this->application->redirect((string) $location, $saveURI);
    }

    /**
     * Redirect back to a URL saved during redirection.
     *
     * This mechanism is used with the $saveURI parameter of `Hazaar\Application::redirect()` so save the current URL into the session
     * so that once we're done processing the request somewhere else we can come back to where we were. This is useful for when
     * a user requests a page but isn't authenticated, we can redirect them to a login page and then that page can call this
     * `Hazaar\Controller::redirectBack()` method to redirect the user back to the page they were originally looking for.
     */
    public function redirectBack(null|string|URL $altURL = null): Response
    {
        return $this->application->redirectBack($altURL);
    }

    /**
     * Generate a URL relative to the controller.
     *
     * This is the controller relative method for generating URLs in your application.  URLs generated from
     * here are relative to the controller.  For URLs that are relative to the current application see
     * `Hazaar\Application::url()`.
     *
     * Parameters are dynamic and depend on what you are trying to generate.
     *
     * For examples see: [Generating URLs](/guide/basics/urls.md)
     */
    public function getURL(): URL
    {
        $url = new URL();
        $parts = func_get_args();
        $thisParts = explode('/', $this->name);
        call_user_func_array([$url, '__construct'], array_merge($thisParts, $parts));

        return $url;
    }

    /**
     * Test if a URL is active, relative to this controller.
     *
     * Parameters are simply a list of URL 'parts' that will be combined to test against the current URL to see if it is active.  Essentially
     * the argument list is the same as `Hazaar\Controller::url()` except that parameter arrays are not supported.
     *
     * * Example
     * ```php
     * if($controller->active('index')){
     * ```
     *
     * If the current URL has more parts than the function argument list, this will mean that only a portion of the URL is tested
     * against.  This allows an action to be tested without looking at it's argument list URL parts.  This also means that it is
     * possible to call the `active()` method without any arguments to test if the controller itself is active, which if you are
     * calling it from within the controller, should always return `TRUE`.
     *
     * @return bool true if the supplied URL is active as the current URL
     */
    public function isActive(): bool
    {
        $parts = func_get_args();

        return call_user_func_array([$this->application, 'active'], array_merge([$this->name], $parts));
    }

    /**
     * Add a helper to the controller.
     *
     * @param array<Helper|string>|Helper|string $helper The helper to add to the controller.  This can be a helper object, a helper class name or an array of helpers.
     * @param array<mixed>                       $args   an array of arguments to pass to the helper constructor
     * @param string                             $alias  The alias to use for the helper.  If not provided, the helper name will be used.
     *
     * @return bool returns true if the helper was added successfully
     */
    public function addHelper(array|Helper|string $helper, array $args = [], ?string $alias = null): bool
    {
        if (is_array($helper)) {
            foreach ($helper as $alias => $h) {
                self::addHelper($h, [], $alias);
            }
        } elseif (is_object($helper)) {
            if (!$helper instanceof Helper) {
                return false;
            }
            if (null === $alias) {
                $alias = strtolower($helper->getName());
            }
            $this->_helpers[$alias] = $helper;
        } elseif (null !== $helper) {
            if (null === $alias) {
                $alias = strtolower($helper);
            }
            if (!array_key_exists($alias, $this->_helpers)) {
                if (!($class = $this->findHelper($helper))) {
                    throw new \Exception("Controller helper '{$helper}' does not exist");
                }
                $obj = new $class($this, $args);
                $this->_helpers[$alias] = $obj;
            } else {
                if (($obj = $this->_helpers[$alias]) instanceof View\Helper) {
                    $obj->extendArgs($args);
                }
            }
        }

        return true;
    }

    /**
     * Checks if a helper exists.
     *
     * @param string $helper the name of the helper to check
     *
     * @return bool returns true if the helper exists, false otherwise
     */
    public function hasHelper($helper): bool
    {
        return array_key_exists($helper, $this->_helpers);
    }

    public function cacheAction(string $actionName, int $timeout = 60, bool $private = false): bool
    {
        if (null === $this->responseCache) {
            $this->responseCache = new Cache();
        }
        $this->getCacheKey($this->name, $actionName, [], $cacheName);
        $this->cachedActions[$cacheName] = [
            'timeout' => $timeout,
            'private' => $private,
        ];

        return true;
    }

    /*s
     * Find a helper class by name.
     *
     * This method searches for view helper classes based on the given name. The search order is important because it allows apps to override built-in helpers.
     *
     * @param string $name the name of the helper class to find
     *
     * @return null|string the fully qualified class name of the helper, or null if not found
     */
    private function findHelper(string $name): ?string
    {
        /**
         * Search paths for view helpers. The order here matters because apps should be able to override built-in helpers.
         */
        $searchPrefixes = ['\Application\Helper\Controller', '\Hazaar\Controller\Helper'];
        $name = \ucfirst($name);
        foreach ($searchPrefixes as $prefix) {
            $class = $prefix.'\\'.$name;
            if (\class_exists($class)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Get the cache key for the current action.
     *
     * @param string       $controller the controller name
     * @param string       $action     the action name
     * @param array<mixed> $actionArgs the action arguments
     * @param null|string  $cacheName  the cache name
     *
     * @return false|string the cache key, or false if the action is not cached
     */
    private function getCacheKey(string $controller, string $action, ?array $actionArgs = null, ?string &$cacheName = null): false|string
    {
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
