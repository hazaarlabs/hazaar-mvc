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
use Hazaar\Cache;
use Hazaar\Controller;
use Hazaar\Loader;

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
    public string $urlDefaultActionName = 'index';
    protected ?string $__action = null;

    /**
     * @var array<mixed>
     */
    protected array $__actionArgs = [];

    /**
     * @var array<mixed>
     */
    protected array $__cachedActions = [];
    protected static ?Cache $__cache = null;
    protected ?string $__cache_key = null;
    protected bool $__stream = false;

    public function __initialize(Request $request): ?Response
    {
        parent::__initialize($request);
        $response = null;
        if (!($this->__action = $request->shiftPath())) {
            $this->__action = $this->urlDefaultActionName;
        }
        $this->init($request);
        $response = $this->initResponse($request);
        if ($request->getPath()) {
            $this->__actionArgs = explode('/', $request->getPath());
        }

        return $response;
    }

    /**
     * Run an action method on a controller.
     *
     * This is the main controller action decision code and is where the controller will decide what to
     * actually execute and whether to cache the response on not.
     *
     * @param string $action The name of the action to run
     *
     * @throws Exception\ActionNotFound
     * @throws Exception\ActionNotPublic
     */
    protected function __runAction(?string &$action = null): ?Response
    {
        if (!$action) {
            $action = $this->__action;
        }
        /*
         * Check if we have routed to the default controller, or the action method does not
         * exist and if not check for the __default() method.  Otherwise we have nothing
         * to execute so throw a nasty exception.
         */
        if (true === $this->application->router->isDefaultController
            || !method_exists($this, $action)) {
            if (method_exists($this, '__default')) {
                array_unshift($this->__actionArgs, $action);
                array_unshift($this->__actionArgs, $this->name);
                $action = '__default';
            } else {
                throw new Exception\ActionNotFound(get_class($this), $action);
            }
        }
        $cache_name = $this->name.'::'.$action;
        // Check the cached actions to see if this requested should use a cached version
        if (Basic::$__cache instanceof Cache && array_key_exists($cache_name, $this->__cachedActions)) {
            $this->__cache_key = $cache_name.'('.serialize($this->__actionArgs).')';
            if (true !== $this->__cachedActions[$cache_name]['public'] && $sid = session_id()) {
                $this->__cache_key .= '::'.$sid;
            }
            if ($response = Basic::$__cache->get($this->__cache_key)) {
                return $response;
            }
        }
        $method = new \ReflectionMethod($this, $action);
        if (!$method->isPublic()) {
            throw new Exception\ActionNotPublic(get_class($this), $action);
        }
        $response = $method->invokeArgs($this, $this->__actionArgs);
        if ($this->__stream) {
            return new Response\Stream($response);
        }
        if (is_array($response)) {
            $response = new Response\JSON($response);
        }

        return $response;
    }

    public function __run(): bool|Response
    {
        $response = $this->__runAction();
        $this->cacheResponse($response);
        $response->setController($this);

        return $response;
    }

    public function cacheAction(string $action, int $timeout = 60, bool $public = false): bool
    {
        if (!Basic::$__cache instanceof Cache) {
            Basic::$__cache = new Cache();
        }
        $this->__cachedActions[$this->name.'::'.$action] = ['timeout' => $timeout, 'public' => $public];

        return true;
    }

    public function getAction(): string
    {
        return $this->__action;
    }

    /**
     * Returns the action arguments for the controller.
     *
     * @return array<mixed> the action arguments
     */
    public function getActionArgs(): array
    {
        return $this->__actionArgs;
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
            $this->__stream = true;
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
     * Forwards an action from the requested controller to another controller.
     *
     * This is some added magic to assist with poorly designed MVC applications where too much "common" code
     * has been implemented in a controller action.  This allows the action request to be forwarded and the
     * response returned.  The target action is executed as though it was called on the requested controller.
     * This means that view data can be modified after the action has executed to modify the response.
     *
     * Note: If you don't need to modify any response data, then it would be more efficient to use an alias.
     *
     * @param string       $controller the name of the controller to forward to
     * @param string       $action     Optional. The name of the action to call on the target controller.  If ommitted, the
     *                                 name of the requested action will be used.
     * @param array<mixed> $actionArgs Optional. An array of arguments to forward to the action.  If ommitted, the arguments
     *                                 sent to the calling action will be forwarded.
     * @param Controller   $target     The target controller.  Allows direct access to the forward controller after it has
     *                                 been loaded.
     *
     * @return Response returns the same return value returned by the forward controller action
     */
    public function forwardAction(string $controller, ?string $action = null, ?array $actionArgs = null, ?Controller &$target = null): Response
    {
        $target = Loader::getInstance()->loadController($controller, $this->name);
        if (!$target instanceof Basic) {
            throw new \Exception("Unable to forward action to controller '{$controller}'.  Target controller must be an instance of \\Hazaar\\Controller\\Basic.");
        }
        if (null === $action) {
            $action = $this->getAction();
        }
        if (null === $actionArgs) {
            $actionArgs = $this->getActionArgs();
        }
        $this->request->pushPath($action);
        $target->__initialize($this->request);

        return call_user_func_array([$target, $action], $actionArgs);
    }

    protected function init(Request $request): void {}

    protected function initResponse(Request $request): ?Response
    {
        return null;
    }

    /**
     * Cache a response to the current action invocation.
     *
     * @param Response $response The response to cache
     */
    protected function cacheResponse(Response $response): void
    {
        $cache_name = $this->name.'::'.$this->__action;
        if (null !== $this->__cache_key && array_key_exists($cache_name, $this->__cachedActions)) {
            Basic::$__cache->set($this->__cache_key, $response, $this->__cachedActions[$cache_name]['timeout']);
        }
    }
}
