<?php

namespace Hazaar\Application\Router\Loader;

use Hazaar\Application\Request;
use Hazaar\Application\Route;
use Hazaar\Application\Router;
use Hazaar\Application\Router\Exception\ControllerHasNoRoutes;
use Hazaar\Date;

/**
 * The annotated router class.
 *
 * This controller can be used to create RESTful API endpoints.  It automatically handles
 * HTTP request methods and can send appropriate responses for invalid requests.  It can
 * also provide an intelligent API endpoint directory.
 *
 * ## Overview
 *
 * Unlike other controllers, the rest controller works using annotations.  Such as:
 *
 * ```php
 * class ApiController extends \Hazaar\Controller\REST {
 *   /**
 *    * @route('/dothething/<int:thingstodo>', methods=['GET'])
 *    **\/
 *   protected function do_the_thing($thingstodo){
 *     return ['things' => 'Array of things'];
 *   }
 * }
 * ```
 *
 * This endpoint will be accessible at the URL: http://yourhost.com/api/v1/dothething/1234
 *
 * ## Versions
 *
 * It is possible to add your own "version control" to the REST api by defining multiple
 * functions for the same route.  Using versions allows you to easily update your API without
 * removing backwards compatibility.  New versions should be used when there is a major change
 * to either the input or output of your endpoint and renaming it is not reasonable.
 *
 * Do define another version of the above example using versions you could:
 *
 * ```php
 * class ApiController extends \Hazaar\Controller\Basic {
 *    * @route('/v1/dothething/<int:thingstodo>', methods=['GET'])
 *    **\/
 *   protected function do_the_thing($thingstodo){
 *     return ['things' => 'Array of things'];
 *   }
 *    * @route('/v2/dothething/<date:when>/<int:thingstodo>', methods=['GET'])
 *    **\/
 *   protected function do_the_thing_v2($thingstodo, $when){
 *     if($when->year() >= 2023){
 *       return ['things' => 'Array of FUTURE things'];
 *     }
 *
 *     return ['things' => 'Array of things'];
 *   }
 * }
 * ```
 *
 * This endpoint will be accessible at the URL: http://yourhost.com/api/v1/dothething/2040-01-01/1234
 *
 * ### Endpoints on multiple versions
 *
 * To allow an endpoint to be available on multiple versions of your API, simply add multple @routes
 *
 * Such as:
 *
 * ```php
 *   * @route('/v1/dothething/<date:when>/<int:thingstodo>', methodsGET'])
 *   * @route('/v2/dothething/<date:when>/<int:thingstodo>', methods=['GET'])
 *   **\/
 * ```
 *
 * ## Endpoint Directories
 * Endpoint directories are simply a list of the available endpoints with some basic information
 * about how they operate such as the HTTP methods allowed, parameter description and a brief description.
 */
class Annotated extends Advanced
{
    /**
     * @var array<string> the valid types for parameters
     */
    protected static array $validTypes = [
        'boolean',
        'bool',
        'integer',
        'int',
        'double',
        'float',
        'string',
        'array',
        'null',
        'date',
    ];

    /**
     * @var array<string, array<mixed>>
     */
    private array $controllerEndpoints = [];

    private bool $describeFull = true;

    /**
     * Private method to describe the REST API.
     *
     * @return array<mixed> returns an array containing the description of the REST API
     */
    public function describeAPI(): array
    {
        $api = [];
        foreach ($this->controllerEndpoints as $endpoint) {
            $this->describeEndpoint($endpoint, $this->describeFull, $api);
        }

        return $api;
    }

    public function exec(Request $request): bool
    {
        parent::exec($request);
        Router::reset();
        if (!$this->controller) {
            return true;
        }
        $this->controllerEndpoints = $this->loadEndpoints($this->controllerClass);
        if (0 === count($this->controllerEndpoints)) {
            throw new ControllerHasNoRoutes($this->controller);
        }
        foreach ($this->controllerEndpoints as $endpoint) {
            if (!array_key_exists('routes', $endpoint)) {
                continue;
            }
            foreach ($endpoint['routes'] as $path => $route) {
                $path = '/'.strtolower($this->controller).'/'.ltrim($path, '/');
                Router::match($route['args']['methods'], $path, $route['func']);
            }
        }

        return true;
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
        foreach ($this->controllerEndpoints as $endpoint) {
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

    /**
     * @return array<mixed>
     */
    private function loadEndpoints(string $controllerClass): array
    {
        $endpoints = [];
        $controllerReflection = new \ReflectionClass($controllerClass);
        foreach ($controllerReflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ('__' === substr($method->getName(), 0, 2) || $method->isPrivate()) {
                continue;
            }
            $method->setAccessible(true);
            if (!($comment = $method->getDocComment())) {
                continue;
            }
            if (!preg_match_all('/\*\s*@((\w+).*)/', $comment, $methodMatches)) {
                continue;
            }
            $endpoint = [
                'cache' => false,
                'cache_ttl' => 60,
                'cache_ignore_params' => false,
                'private' => false,
                'comment' => $comment,
                'routes' => [],
            ];
            if (($pos = array_search('cache', $methodMatches[2])) !== false) {
                if (preg_match('/cache\s+(.*)/', $methodMatches[1][$pos], $cacheMatches)) {
                    $cache = trim($cacheMatches[1]);
                    if (is_numeric($cache)) {
                        $endpoint['cache'] = true;
                        $endpoint['cache_ttl'] = (int) $cache;
                    } else {
                        $endpoint['cache'] = boolify($cache);
                    }
                    if (true === $endpoint['cache']
                        && (($pos = array_search('cache_ignore_params', $methodMatches[2])) !== false)
                        && preg_match('/cache_ignore_params\s+(.*)/', $methodMatches[1][$pos], $cacheMatches)) {
                        $endpoint['cache_ignore_params'] = boolify($cacheMatches[1]);
                    }
                }
            }
            foreach ($methodMatches[2] as $index => $tag) {
                if ('route' !== $tag) {
                    continue;
                }
                if (!preg_match(
                    '/\([\'\"]([\w\.\-\<\>\:\/]+)[\'\"]\s*,?\s*(.+)*\)/',
                    $methodMatches[1][$index],
                    $routeMatches
                )) {
                    continue;
                }
                $target = '/'.ltrim($routeMatches[1], '/');
                $args = ['methods' => ['GET']];
                if (array_key_exists(2, $routeMatches)) {
                    $parts = preg_split('/\s*(,(?![^(\[]*[\)\]]))\s*/', $routeMatches[2]);
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
            $endpoint['private'] = in_array('private', $methodMatches[2]);
            if (count($endpoint['routes']) > 0) {
                $endpoints[$method->name] = $endpoint;
            }
        }

        return $endpoints;
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
    private function describeEndpoint(array $endpoint, bool $describe_full = false, ?array &$api = null): array
    {
        if (!$api) {
            $api = [];
        }
        foreach ($endpoint['routes'] as $route => $route_data) {
            foreach ($route_data['args']['methods'] as $methods) {
                $info = [
                    // 'url' => (string) $this->url($route),
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
}
