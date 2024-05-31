<?php

declare(strict_types=1);

/**
 * @file        Controller/REST.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2017 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar\Controller;

use Hazaar\Application\Request;
use Hazaar\Application\Request\HTTP;
use Hazaar\Controller;
use Hazaar\Controller\Exception\ActionNotFound;
use Hazaar\Controller\Response\JSON;

abstract class REST extends Controller
{
    /**
     * The request object.
     *
     * @var HTTP
     */
    protected Request $request;

    /**
     * Initializes the REST controller.
     *
     * @throws \Exception if there is an invalid JSON parameter
     */
    public function __initialize(Request $request): ?Response
    {
        if (!$request instanceof HTTP) {
            throw new \Hazaar\Exception('REST controllers require an HTTP request object!');
        }
        parent::__initialize($request);
        $this->init($request);
        $response = $this->initResponse($request);
        if (null !== $response) {
            return $response;
        }
        $this->router->application->setResponseType('json');

        /*
         *  if ('OPTIONS' == $this->request->method()) {
         * $response = new JSON();
         * $response->setHeader('allow', $route['args']['methods']);
         * if ($this->allow_directory) {
         * $response->populate($this->__describe_endpoint($route, $this->describe_full));
         * }
         *
         * return $response;
         * }
         */
        return null;
    }

    /**
     * This method runs the REST API endpoint by matching the request method and path with the available endpoints.
     * If a match is found, it sets the endpoint and its arguments and breaks the loop.
     * If the request method is OPTIONS, it returns a JSON response with the allowed methods for the endpoint.
     * If the endpoint is not found, it throws a 404 exception.
     * If the endpoint is found and the 'init' method exists, it calls the 'init' method and returns its response if it is an instance of \Hazaar\Controller\Response.
     * Otherwise, it executes the endpoint with its arguments and returns the response.
     *
     * @return Response the response of the endpoint or a JSON response with the allowed methods for the endpoint
     *
     * @throws \Hazaar\Exception if the endpoint is not found or directory listing is not allowed
     */
    public function __runAction(string $actionName, array $actionArgs = [], bool $namedActionArgs = false): Response
    {
        if (!method_exists($this, $actionName)) {
            throw new ActionNotFound(get_class($this), $actionName);
        }

        try {
            $actionReflection = new \ReflectionMethod($this, $actionName);
        } catch (\ReflectionException $e) {
            throw new ActionNotFound(get_class($this), $actionName);
        }
        $params = [];
        if (true === $namedActionArgs) {
            foreach ($actionReflection->getParameters() as $p) {
                $key = $p->getName();
                $value = null;
                if (array_key_exists($key, $actionArgs)) {
                    $value = $actionArgs[$key];
                } elseif ($p->isDefaultValueAvailable()) {
                    $value = $p->getDefaultValue();
                } else {
                    throw new \Hazaar\Exception("Missing value for parameter '{$key}'.", 400);
                }
                $params[$p->getPosition()] = $value;
            }
        } else {
            $params = $actionArgs;
        }
        $result = $actionReflection->invokeArgs($this, $params);
        if ($result instanceof Response) {
            return $result;
        }

        return new JSON($result);
    }

    /**
     * Initializes the REST controller.
     */
    protected function init(HTTP $request): void
    {
        // do nothing
    }

    protected function initResponse(HTTP $request): ?Response
    {
        return null;
    }
}
