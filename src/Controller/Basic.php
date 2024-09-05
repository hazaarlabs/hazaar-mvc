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
    protected bool $stream = false;

    public function initialize(Request $request): ?Response
    {
        parent::initialize($request);
        $this->init($request);

        return $this->initResponse($request);
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

    protected function init(Request $request): void {}

    protected function initResponse(Request $request): ?Response
    {
        return null;
    }
}
