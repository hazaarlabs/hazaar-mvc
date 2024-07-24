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
use Hazaar\Application\Router;
use Hazaar\Application\URL;
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
    public string $urlDefaultActionName = 'index';
    protected Router $router;
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
     * Base controller constructor.
     *
     * @param string $name The name of the controller.  This is the name used when generating URLs.
     */
    public function __construct(Router $router, ?string $name = null)
    {
        $this->router = $router;
        $this->application = $router->application;
        $this->name = strtolower(null !== $name ? $name : get_class($this));
        $this->addHelper('response');
        $this->urlDefaultActionName = $router->getDefaultActionName();
    }

    /**
     * Controller shutdown method.
     *
     * This method is called when a controller is being shut down.  It will call the extending controllers
     * shutdown method if it exists, otherwise it will silently carry on.
     */
    public function __shutdown(Response $response): void
    {
        $this->shutdown($response);
    }

    /**
     * Controller initialisation method.
     *
     * This should be called by all extending controllers and is simply responsible for storing the calling request.
     *
     * @param Request $request the application request object
     */
    public function __initialize(Request $request): ?Response
    {
        $this->request = $request;

        return null;
    }

    /**
     * Convert the controller object into a string.
     */
    public function __toString(): string
    {
        return get_class($this);
    }

    /**
     * Default run method.
     *
     * The run method is where the controller does all it's work.  This default one does nothing.
     */
    public function __run(): false|Response
    {
        return false;
    }

    public function __runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): false|Response
    {
        return false;
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
     * Shutdown the controller.
     *
     * This method is called when the controller is being shut down.
     *
     * @param Response $response the response object
     *
     * @return bool returns true
     */
    public function shutdown(Response $response): bool
    {
        return true;
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
        return $this->router->application->redirect((string)$location, $saveURI);
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
        return $this->router->application->redirectBack($altURL);
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
    public function url(): URL
    {
        $url = new URL();
        $parts = func_get_args();
        if (1 === count($parts) && strtolower(trim($parts[0] ?? '')) === $this->urlDefaultActionName) {
            $parts = [];
        }
        $thisParts = explode('/', $this->name);
        if (0 === count($parts) && $thisParts[count($thisParts) - 1] === $this->urlDefaultActionName) {
            array_pop($thisParts);
        }
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
    public function active(): bool
    {
        $parts = func_get_args();

        return call_user_func_array([$this->router->application, 'active'], array_merge([$this->name], $parts));
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

    public function cacheAction(string $action, int $timeout = 0): void
    {
        $this->router->cacheAction($this->name, $action, $timeout);
    }

    /**
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
        $searchPrefixes = ['\\Application\\Helper\\Controller', '\\Hazaar\\Controller\\Helper'];
        $name = \ucfirst($name);
        foreach ($searchPrefixes as $prefix) {
            $class = $prefix.'\\'.$name;
            if (\class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}
