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
use Hazaar\Controller\Helper;
use Hazaar\Controller\Response;
use Hazaar\Controller\Response\HTTP\NotImplemented;
use Hazaar\Controller\Response\HTTP\Redirect;

/**
 * Base Controller class.
 *
 * All controller classes extend this class.  Normally this class would only be extended by the controller classes
 * provided by Hazaar, as how a controller actually behaves and the functionality it provides is actually defined
 * by the controller itself.  This controller does nothing, but will still initialise and run, but will output nothing.
 *
 * @property Helper\Response $response
 */
abstract class Controller implements Controller\Interface\Controller
{
    protected string $name = 'controller';
    protected Request $request;
    protected int $statusCode = 0;
    protected string $basePath;    // Optional basePath for controller relative url() calls.

    /**
     * @var array<mixed>
     */
    private array $_helpers = [];

    private static string $redirectCookieName = 'hazaar-redirect-token';

    /**
     * Convert the controller object into a string.
     */
    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Get the specified helper object.
     *
     * @param string $helper the name of the helper
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
    public function initialize(Request $request): void
    {
        $this->name = strtolower(get_class($this));
        $this->request = $request;
        $this->addHelper('response');
    }

    public function run(?Route $route = null): Response
    {
        return new NotImplemented();
    }

    public function shutdown(): void
    {
        // This method can be overridden by extending controllers to perform any necessary cleanup.
    }

    /**
     * Get the name of the controller.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the default return status code.
     */
    public function setStatus(?int $code = null): void
    {
        $this->statusCode = $code;
    }

    /**
     * Get the status code of the controller.
     */
    public function getStatus(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the base path of the controller.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set the base path for the controller.
     */
    public function setBasePath(string $path): void
    {
        $this->basePath = $path;
    }

    /**
     * Generate a redirect response to redirect the browser.
     *
     * It's quite common to redirect the user to an alternative URL. This may be to forward the request
     * to another website, forward them to an authentication page or even just remove processed request
     * parameters from the URL to neaten the URL up.
     *
     * @param string $location The URI you want to redirect to
     * @param bool   $saveURI  Optionally save the URI so we can redirect back. See: `Hazaar\Application::redirectBack()`
     */
    public function redirect(string $location, bool $saveURI = false): Redirect
    {
        $headers = apache_request_headers();
        if (array_key_exists('X-Requested-With', $headers) && 'XMLHttpRequest' === $headers['X-Requested-With']) {
            echo "<script>document.location = '{$location}';</script>";
        } else {
            if ($saveURI) {
                $data = [
                    'URI' => $_SERVER['REQUEST_URI'],
                    'METHOD' => $_SERVER['REQUEST_METHOD'],
                ];
                if ('POST' === $_SERVER['REQUEST_METHOD']) {
                    $data['POST'] = $_POST;
                }
                setcookie(self::$redirectCookieName, base64_encode(serialize($data)), time() + 3600, '/');
            }
        }

        return new Redirect($location);
    }

    /**
     * Redirect back to a URI saved during redirection.
     *
     * This mechanism is used with the $saveURI parameter of `Hazaar\Application::redirect()` so save the current
     * URI into the session so that once we're done processing the request somewhere else we can come back
     * to where we were. This is useful for when a user requests a page but isn't authenticated, we can
     * redirect them to a login page and then that page can call this `Hazaar\Application::redirectBack()` method to redirect the
     * user back to the page they were originally looking for.
     */
    public function redirectBack(?string $altURL = null): false|Redirect
    {
        if (array_key_exists(self::$redirectCookieName, $_COOKIE)) {
            $data = unserialize(base64_decode($_COOKIE[self::$redirectCookieName]));
            if (($uri = $data['URI'] ?? null) && 'POST' === ($data['METHOD'] ?? null)) {
                $uri .= '?' !== substr($uri, -1, 1) ? '?' : '&';
                $uri .= http_build_query($data['POST']);
            }
            setcookie(self::$redirectCookieName, '', time() - 3600, '/');
        } else {
            $uri = $altURL;
        }
        if ($uri) {
            return new Redirect($uri);
        }

        return false;
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
     * For examples see: [Generating URLs](/docs/basics/urls.md)
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
     * Add a helper to the controller.
     *
     * @param array<Helper|string>|Helper|string $helper The helper to add to the controller.  This can be a helper object, a helper class name or an array of helpers.
     * @param array<mixed>                       $args   an array of arguments to pass to the helper constructor
     * @param string                             $alias  The alias to use for the helper.  If not provided, the helper name will be used.
     */
    public function addHelper(array|Helper|string $helper, array $args = [], ?string $alias = null): bool
    {
        if (is_array($helper)) {
            foreach ($helper as $alias => $h) {
                self::addHelper($h, [], $alias);
            }
        } elseif (is_object($helper)) {
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
     */
    public function hasHelper($helper): bool
    {
        return array_key_exists($helper, $this->_helpers);
    }

    /**
     * Test if a URL is active, relative to the application base URL.
     *
     * Parameters are simply a list of URL 'parts' that will be combined to test against the current URL to see if it is active.  Essentially
     * the argument list is the same as `Hazaar\Application::url()` except that parameter arrays are not supported.
     *
     * Unlike `Hazaar\Controller::active()` this method tests if the path is active relative to the application base path.  If you
     * want to test if a particular controller is active, then it has to be the first argument.
     *
     * * Example
     * ```php
     * $application->active('mycontroller');
     * ```
     *
     * @return bool true if the supplied URL is active as the current URL
     */
    public function isActive(): bool
    {
        $parts = [];
        foreach (func_get_args() as $part) {
            $partParts = strpos($part, '/') ? array_map('strtolower', array_map('trim', explode('/', $part))) : [$part];
            foreach ($partParts as $partPart) {
                $parts[] = strtolower(trim($partPart ?? ''));
            }
        }
        $basePath = $this->request->getPath();
        $requestParts = $basePath ? array_map('strtolower', array_map('trim', explode('/', $basePath))) : [];
        for ($i = 0; $i < count($parts); ++$i) {
            if ($parts[$i] !== $requestParts[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Find a helper class by name.
     *
     * This method searches for view helper classes based on the given name. The search order is important because it allows apps to override built-in helpers.
     *
     * @param string $name the name of the helper class to find
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
}
