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
use Hazaar\Cache;
use Hazaar\Controller;
use Hazaar\Controller\Response\JSON;
use Hazaar\Date;

/**
 * @brief       The RESTful controller class
 *
 * @detail      This controller can be used to create RESTful API endpoints.  It automatically handles
 *              HTTP request methods and can send appropriate responses for invalid requests.  It can
 *              also provide an intelligent API endpoint directory.
 *
 *              ## Overview
 *              Unlike other controllers, the rest controller works using annotations.  Such as:
 *
 *              ```php
 *              class ApiController extends \Hazaar\Controller\REST {
 *                  /**
 *                   * @route('/dothething/<int:thingstodo>', methods=['GET'])
 *                   **\/
 *                  protected function do_the_thing($thingstodo){
 *
 *                      return ['things' => 'Array of things'];
 *
 *                  }
 *
 *              }
 *              ```
 *
 *              This API will be accessible at the URL: http://yourhost.com/api/v1/dothething/1234
 *
 *              ## Versions
 *              It is possible to add your own "version control" to the REST api by defining multiple
 *              functions for the same route.  Using versions allows you to easily update your API without
 *              removing backwards compatibility.  New versions should be used when there is a major change
 *              to either the input or output of your endpoint and renaming it is not reasonable.
 *
 *              Do define another version of the above example using versions you could:
 *
 *              ```php
 *              class ApiController extends \Hazaar\Controller\REST {
 *                   * @route('/v1/dothething/<int:thingstodo>', methods=['GET'])
 *                   **\/
 *                  protected function do_the_thing($thingstodo){
 *
 *                      return ['things' => 'Array of things'];
 *
 *                  }
 *                   * @route('/v2/dothething/<date:when>/<int:thingstodo>', methods=['GET'])
 *                   **\/
 *                  protected function do_the_thing_v2($thingstodo, $when){
 *
 *                      if($when->year() >= 2023)
 *                          return ['things' => 'Array of FUTURE things'];
 *
 *                      return ['things' => 'Array of things'];
 *
 *                  }
 *
 *              }
 *              ```
 *
 *              This API will be accessible at the URL: http://yourhost.com/api/v1/dothething/2040-01-01/1234
 *
 *              ### Endpoints on multiple versions
 *              To allow an endpoint to be available on multiple versions of your API, simply add multple @routes
 *
 *              Such as:
 *
 *              ```php
 *                * @route('/v1/dothething/<date:when>/<int:thingstodo>', methodsGET'])
 *                * @route('/v2/dothething/<date:when>/<int:thingstodo>', methods=['GET'])
 *               **\/
 *              ```
 *
 *              ## Endpoint Directories
 *              Endpoint directories are simply a list of the available endpoints with some basic information
 *              about how they operate such as the HTTP methods allowed, parameter description and a brief description.
 */
abstract class REST extends Controller
{
    protected bool $describe_full = true;
    protected bool $allow_directory = false;

    /**
     * @var array<mixed> the list of REST endpoints
     */
    protected array $__rest_endpoints = [];

    /**
     * @var array<mixed> the current REST endpoint
     */
    protected ?array $__endpoint = null;

    /**
     * @var Cache the cache object
     */
    protected Cache $__rest_cache;
    protected bool $__rest_cache_enable_global = false;

    /**
     * @var array<string> the valid types for parameters
     */
    protected static array $valid_types = ['boolean', 'bool', 'integer', 'int', 'double', 'float', 'string', 'array', 'null', 'date'];

    /**
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
        $this->__rest_cache = new Cache();
        $this->application->setResponseType('json');

        $class = new \ReflectionClass($this);
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ('__' === substr($method->getName(), 0, 2) || $method->isPrivate()) {
                continue;
            }
            $method->setAccessible(true);
            if (!($comment = $method->getDocComment())) {
                continue;
            }
            if (!preg_match_all('/\*\s*@((\w+).*)/', $comment, $method_matches)) {
                continue;
            }
            $endpoint = [
                'cache' => false,
                'cache_timeout' => null,
                'cache_ignore_params' => false,
                'comment' => $comment,
                'routes' => [],
            ];
            if (($pos = array_search('cache', $method_matches[2])) !== false) {
                if (preg_match('/cache\s+(.*)/', $method_matches[1][$pos], $cache_matches)) {
                    $cache = trim($cache_matches[1]);
                    if (is_numeric($cache)) {
                        $endpoint['cache'] = true;
                        $endpoint['cache_timeout'] = (int)$cache;
                    } else {
                        $endpoint['cache'] = boolify($cache);
                    }
                    if (true === $endpoint['cache']
                        && (($pos = array_search('cache_ignore_params', $method_matches[2])) !== false)
                        && preg_match('/cache_ignore_params\s+(.*)/', $method_matches[1][$pos], $cache_matches)) {
                        $endpoint['cache_ignore_params'] = boolify($cache_matches[1]);
                    }
                }
            }
            foreach ($method_matches[2] as $index => $tag) {
                if ('route' !== $tag) {
                    continue;
                }
                if (!preg_match('/\([\'\"]([\w\.\-\<\>\:\/]+)[\'\"]\s*,?\s*(.+)*\)/', $method_matches[1][$index], $route_matches)) {
                    continue;
                }
                $target = '/'.ltrim($route_matches[1], '/');
                $args = ['methods' => ['GET']];
                if (array_key_exists(2, $route_matches)) {
                    $parts = preg_split('/\s*(,(?![^(\[]*[\)\]]))\s*/', $route_matches[2]);
                    foreach ($parts as $part) {
                        // If there is no equals sign, skip this one.
                        if (strpos($part, '=') > 0) {
                            list($key, $value) = explode('=', $part, 2);
                            if (($value = json_decode(str_replace("'", '"', $value), true)) === null) {
                                throw new \Exception('Invalid JSON parameter for: '.$key);
                            }
                            $args[$key] = $value;
                        } else {
                            if (!array_key_exists('properties', $args)) {
                                $args['properties'] = [];
                            }
                            $args['properties'][] = trim($part);
                        }
                    }
                }
                $endpoint['routes'][$target] = [
                    'func' => $method,
                    'args' => $args,
                ];
            }
            if (count($endpoint['routes']) > 0) {
                $this->__rest_endpoints[$method->name] = $endpoint;
            }
        }

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
    public function __run(): Response
    {
        $full_path = '/'.$this->request->getPath();
        $request_method = $this->request->method();
        foreach ($this->__rest_endpoints as $endpoint) {
            foreach ($endpoint['routes'] as $target => $route) {
                if ($this->__match_route($request_method, $full_path, $target, $route, $args)) {
                    if ('OPTIONS' == $this->request->method()) {
                        $response = new JSON();
                        $response->setHeader('allow', $route['args']['methods']);
                        if ($this->allow_directory) {
                            $response->populate($this->__describe_endpoint($route, $this->describe_full));
                        }

                        return $response;
                    }
                    $this->__endpoint = [$endpoint, $route, $args];

                    break 2;
                }
            }
        }
        if (null === $this->__endpoint) {
            if (is_array($full_path)) {
                $full_path = implode('/', $full_path);
            }
            if ('' === $full_path) {
                if (!$this->allow_directory) {
                    throw new \Hazaar\Exception('Directory listing is not allowed', 403);
                }

                return new JSON($this->__describe_api());
            }

            throw new \Hazaar\Exception('REST API Endpoint not found: '.$full_path, 404);
        }
        $response = $this->init($this->request);
        if (null !== $response) {
            return $response;
        }

        return $this->__exec_endpoint($this->__endpoint, $this->request->getParams());
    }

    /**
     * Private method to describe the REST API.
     *
     * @return array<mixed> returns an array containing the description of the REST API
     */
    private function __describe_api(): array
    {
        $api = [];
        foreach ($this->__rest_endpoints as $endpoint) {
            $this->__describe_endpoint($endpoint, $this->describe_full, $api);
        }

        return $api;
    }

    /**
     * Describes an endpoint by generating an array of information about the endpoint.
     *
     * @param array<mixed> $endpoint      the endpoint to describe
     * @param bool         $describe_full whether to include full description or not
     * @param array<mixed> $api           the array to append the endpoint information to
     *
     * @return array<mixed> the array of information about the endpoint
     */
    private function __describe_endpoint(array $endpoint, bool $describe_full = false, ?array &$api = null): array
    {
        if (!$api) {
            $api = [];
        }
        foreach ($endpoint['routes'] as $route => $route_data) {
            foreach ($route_data['args']['methods'] as $methods) {
                $info = [
                    'url' => (string) $this->url($route),
                    'httpMethods' => $methods,
                ];
                if ($describe_full && ($doc = ake($endpoint, 'doc'))) {
                    if ($doc->hasTag('param')) {
                        $info['parameters'] = [];
                        foreach ($doc->tag('param') as $name => $param) {
                            if (!preg_match('/<(\w+:)*'.substr($name, 1).'>/', $route)) {
                                continue;
                            }
                            $info['parameters'][$name] = $param['desc'];
                        }
                    }
                    if ($brief = $doc->brief()) {
                        $info['description'] = $brief;
                    }
                }
                $api[] = $info;
            }
        }

        return $api;
    }

    /**
     * Matches the given request method and path to the specified route and endpoint.
     *
     * @param string       $request_method the HTTP request method
     * @param mixed        $path           the path to match against the route
     * @param string       $route          the route to match against the path
     * @param array<mixed> $endpoint       the endpoint to match against the path
     * @param array<mixed> $args           the arguments extracted from the path
     *
     * @return bool returns true if the request method and path match the route and endpoint, false otherwise
     */
    private function __match_route(string $request_method, mixed &$path, string $route, array $endpoint, ?array &$args = null): bool
    {
        $args = [];
        if (!is_array($path)) {
            $path = explode('/', ltrim($path, '/'));
        }
        $route = explode('/', ltrim($route, '/'));
        if (count($path) !== count($route)) {
            return false;
        }
        for ($i = 0; $i < count($path); ++$i) {
            // If this part is identical to the route part, it matches so keep checking
            if ($path[$i] === $route[$i]) {
                continue;
            }
            // If this part of the route is not a variable, there's no match to return
            if (!preg_match('/\<(\w+)(:(\w+))?\>/', $route[$i], $matches)) {
                return false;
            }
            $value = $path[$i];
            if (4 == count($matches)) {
                $key = $matches[3];
                if (!in_array($matches[1], REST::$valid_types)
                    || ('string' === $matches[1] && is_numeric($value))
                    || (('int' === $matches[1] || 'float' === $matches[1]) && !is_numeric($value))
                    || ('bool' === $matches[1] && !is_boolean($value))) {
                    return false;
                }
                if ('date' === $matches[1]) {
                    $value = new Date($value.' 00:00:00');
                } elseif ('timestamp' === $matches[1]) {
                    $value = new Date($value);
                } elseif ('bool' === $matches[1] || 'boolean' === $matches[1]) {
                    $value = boolify($value);
                } else {
                    settype($value, $matches[1]);
                }
                if ('string' === $matches[1] && '' === $value) {
                    return false;
                }
            } else {
                $key = $matches[1];
            }
            $args[$key] = $value;
        }
        $http_methods = ake(ake($endpoint, 'args'), 'methods', ['GET']);
        if (!in_array($request_method, $http_methods)) {
            return false;
        }

        return true;
    }

    /**
     * Executes a REST endpoint with the given arguments.
     *
     * @param array<mixed> $endpoint the endpoint to execute
     * @param array<mixed> $args     the arguments to pass to the endpoint
     *
     * @return Response the response from the endpoint
     *
     * @throws \Hazaar\Exception if the method is no longer a method or if a required parameter is missing
     */
    private function __exec_endpoint(array $endpoint, array $args = []): Response
    {
        list($endpoint, $route, $args) = $endpoint;
        if (!($method = $route['func']) instanceof \ReflectionMethod) {
            throw new \Hazaar\Exception('Method is no longer a method!?', 500);
        }
        $params = [];
        foreach ($method->getParameters() as $p) {
            $key = $p->getName();
            $value = null;
            if (array_key_exists($key, $args)) {
                $value = $args[$key];
            } elseif (array_key_exists('defaults', $route['args']) && array_key_exists($key, $route['args']['defaults'])) {
                $value = $route['args']['defaults'][$key];
            } elseif ($p->isDefaultValueAvailable()) {
                $value = $p->getDefaultValue();
            } else {
                throw new \Hazaar\Exception("Missing value for parameter '{$key}'.", 400);
            }
            $params[$p->getPosition()] = $value;
        }
        $result = null;
        /*
         * Enable caching if:
         * * The cache object has been created (ie: the cache library is available)
         * * This endpoint has enabled caching specifically
         * * Global cache is enabled and this endpoint has not disabled caching
         */
        $cache_key = ($this->__rest_cache instanceof Cache
            && (true === $endpoint['cache']
            || (true === $this->__rest_cache_enable_global && false !== $endpoint['cache']))
            ? 'rest_endpoint_'.md5(serialize([(array) $method, $params, $args, (true !== $endpoint['cache_ignore_params']) ? $this->request->getParams() : null])) : null);
        // Try extracting from cache first if caching is enabled
        if (null !== $cache_key) {
            $result = $this->__rest_cache->get($cache_key);
        }
        // If there is no result yet, execute the endpoint
        if (!$result) {
            $result = $method->invokeArgs($this, $params);
            if ($result instanceof Response) {
                return $result;
            }
            // Save the result if caching is enabled.
            if (null !== $cache_key) {
                $this->__rest_cache->set($cache_key, $result, $endpoint['cache_timeout']);
            }
        }

        return new JSON($result);
    }

    /**
     * Enables or disables caching for the REST endpoint.
     *
     * @param bool $value whether to enable or disable caching
     */
    protected function enableEndpointCaching(bool $value): void
    {
        $this->__rest_cache_enable_global = $value;
    }

    /**
     * Initializes the REST controller.
     */
    protected function init(HTTP $request): ?Response
    {
        return null;
    }

    /**
     * Returns the name of the requested endpoint.
     *
     * @return string the name of the requested endpoint
     */
    protected function getRequestedEndpoint()
    {
        return $this->__endpoint[1]['func']->name;
    }

    /**
     * Get the tags for a given endpoint name.
     *
     * @param string $name the name of the endpoint
     *
     * @return array<string>|false the tags for the endpoint or false if not found
     */
    protected function getEndpointTags(string $name): array|false
    {
        foreach ($this->__rest_endpoints as $endpoint) {
            foreach ($endpoint['routes'] as $route) {
                if ($route['func']->name === $name) {
                    if (!array_key_exists('tags', $route)) {
                        $route['tags'] = [];
                        preg_match_all('/\*\s*@((\w+).*)/', $endpoint['comment'], $matches);
                        foreach ($matches[1] as $annotation) {
                            if (!preg_match('/^(\w+)(\W.*)$/', $annotation, $parts)) {
                                continue;
                            }
                            $route['tags'][$parts[1]] = trim($parts[2]);
                        }
                    }

                    return $route['tags'];
                }
            }
        }

        return false;
    }
}
