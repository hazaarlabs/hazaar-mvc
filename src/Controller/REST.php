<?php
/**
 * @file        Controller/REST.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2017 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

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
 *              <code>
 *              class ApiController extends \Hazaar\Controller\REST {
 *
 *                  /**
 *                   * @route('/dothething/<int:thingstodo>', method=['GET'])
 *                   **\/
 *                  protected function do_the_thing($thingstodo){
 *
 *                      return array('things' => 'Array of things');
 *
 *                  }
 *
 *              }
 *              </code>
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
 *              <code>
 *              class ApiController extends \Hazaar\Controller\REST {
 *
 *                  /**
 *                   * @route('/v1/dothething/<int:thingstodo>', method=['GET'])
 *                   **\/
 *                  protected function do_the_thing($thingstodo){
 *
 *                      return array('things' => 'Array of things');
 *
 *                  }
 *
 *                  /**
 *                   * @route('/v2/dothething/<date:when>/<int:thingstodo>', method=['GET'])
 *                   **\/
 *                  protected function do_the_thing_v2($thingstodo, $when){
 *
 *                      if($when->year() >= 2023)
 *                          return array('things' => 'Array of FUTURE things');
 *
 *                      return array('things' => 'Array of things');
 *
 *                  }
 *
 *              }
 *              </code>
 *
 *              This API will be accessible at the URL: http://yourhost.com/api/v1/dothething/2040-01-01/1234
 *
 *              ### Endpoints on multiple versions
 *              To allow an endpoint to be available on multiple versions of your API, simply add multple @routes
 *
 *              Such as:
 *
 *              <code>
 *              /**
 *                * @route('/v1/dothething/<date:when>/<int:thingstodo>', method=['GET'])
 *                * @route('/v2/dothething/<date:when>/<int:thingstodo>', method=['GET'])
 *               **\/
 *
 *              ## Endpoint Directories
 *              Endpoint directories are simply a list of the available endpoints with some basic information
 *              about how they operate such as the HTTP method, parameter description and a brief description.
 */
abstract class REST extends \Hazaar\Controller {

    protected $describe_full = true;

    protected $allow_directory = true;

    private $__rest_endpoints = array();

    private $__rest_cache;

    private $__rest_cache_enable_global = false;

    protected function enableEndpointCaching($boolean){

        $this->__rest_cache_enable_global = boolify($boolean);

    }

    public function __construct($name, $application, $use_app_config = true) {

        parent::__construct($name, $application, $use_app_config);

        if(class_exists('Hazaar\Cache'))
            $this->__rest_cache = new \Hazaar\Cache();

    }

    public function __initialize(\Hazaar\Application\Request $request) {

        $request->setResponseType('json');

        try{

            $class = new \ReflectionClass($this);

            foreach($class->getMethods() as $method){

                if($method->isPrivate())
                    continue;

                $method->setAccessible(true);

                $doc = new \Hazaar\Parser\DocBlock($method->getDocComment());

                if(!$doc->hasTag('route'))
                    continue;

                foreach($doc->tag('route') as $tag){

                    if(!preg_match('/\([\'\"]([\w\<\>\:\/]+)[\'\"]\s*,?\s*(.+)*\)/', $tag, $matches))
                        continue;

                    $route = '/' . ltrim($matches[1], '/');

                    $args = array('method' => array('GET'));

                    if(array_key_exists(2, $matches)){

                        $parts = preg_split('/\s*(,(?![^(\[]*[\)\]]))\s*/', $matches[2]);

                        foreach($parts as $part){

                            //If there is no equals sign, skip this one.
                            if(strpos($part, '=') > 0){

                                list($key, $value) = explode('=', $part, 2);

                                if($value = json_decode(str_replace("'", '"', $value), true))
                                    $args[$key] = $value;

                            }else{

                                if(!array_key_exists('properties', $args))
                                    $args['properties'] = array();

                                $args['properties'][] = trim($part);

                            }

                        }

                    }

                    if(!array_key_exists($route, $this->__rest_endpoints))
                        $this->__rest_endpoints[$route] = array();

                    $this->__rest_endpoints[$route][] = array(
                        'func' => $method,
                        'doc' => $doc,
                        'args' => $args,
                        'cache' => $doc->hasTag('cache') ? boolify($doc->tag('cache')[0]) : false
                    );

                }

            }

            if(method_exists($this, 'init'))
                $this->init($request);

        }
        catch(\Exception $e){

            return $this->__exception($e);

        }

        return null;

    }

    public function __run() {

        try{

            $full_path = '/' . $this->request->getRawPath();

            if($full_path == '/'){

                if(!$this->allow_directory)
                    throw new \Exception('Directory listing is not allowed', 403);

                return new \Hazaar\Controller\Response\Json($this->__describe_api());

            }

            foreach($this->__rest_endpoints as $route => $routes){

                foreach($routes as $endpoint){

                    if($this->__match_route($full_path, $route, $endpoint, $args)){

                        if($this->request->method() == 'OPTIONS'){

                            $response = new \Hazaar\Controller\Response\Json();

                            $response->setHeader('allow', $endpoint['args']['method']);

                            if($this->allow_directory)
                                $response->populate($this->__describe_endpoint($route, $endpoint, $this->describe_full));

                            return $response;

                        }else
                            return $this->__exec_endpoint($endpoint, $args);

                    }

                }

            }

            throw new \Exception('REST API Endpoint not found: ' . $full_path, 404);

        }
        catch(\Exception $e){

            return $this->__exception($e);

        }

    }

    private function __describe_api(){

        $api = array();

        foreach($this->__rest_endpoints as $route => $routes){

            foreach($routes as $endpoint)
                $this->__describe_endpoint($route, $endpoint, $this->describe_full, $api);

        }

        return $api;

    }

    private function __describe_endpoint($route, $endpoint, $describe_full = false, &$api = null){

        if(!$api) $api = array();

        foreach($endpoint['args']['method'] as $method){

            $info = array(
                'url' => $this->url() . $route,
                'httpMethod' => $method
            );

            if($describe_full && ($doc = ake($endpoint, 'doc'))){

                if($doc->hasTag('param')){

                    $info['parameters'] = array();

                    foreach($doc->tag('param') as $name => $param){

                        if(!preg_match('/<(\w+:)*' . substr($name, 1) . '>/', $route))
                            continue;

                        $info['parameters'][$name] = $param['desc'];

                    }

                }

                if($brief = $doc->brief())
                    $info['description'] = $brief;

            }

            $api[] = $info;

        }

        return $api;

    }

    private function __match_route($path, $route, $endpoint, &$args = null){

        $args = array();

        $valid_types = array('boolean', 'bool', 'integer', 'int', 'double', 'float', 'string', 'array', 'null', 'date');

        $path = explode('/', ltrim($path, '/'));

        $route = explode('/', ltrim($route, '/'));

        if(count($path) != count($route))
            return false;

        for($i = 0; $i < count($path); $i++){

            //If this part is identical to the route part, it matches so keep checking
            if($path[$i] === $route[$i])
                continue;

            //If this part of the route is not a variable, there's no match to return
            if(!preg_match('/\<(\w+)(:(\w+))?\>/', $route[$i], $matches))
                return false;

            $value = $path[$i];

            if(count($matches) == 4){

                $key = $matches[3];

                if(!in_array($matches[1], $valid_types))
                    return false;

                if($matches[1] == 'date')
                    $value = new \Hazaar\Date($value);
                else
                    settype($value, $matches[1]);


            }else{

                $key = $matches[1];

            }

            $args[$key] = $value;

        }

        $http_methods = ake(ake($endpoint, 'args'), 'method', array('GET'));

        if(!in_array($this->request->method(), $http_methods))
            return false;

        return true;

    }

    private function __exec_endpoint($endpoint, $args){

        try{

            if(!($method = $endpoint['func']) instanceof \ReflectionMethod)
                throw new \Exception('Method is no longer a method!?', 500);

            $params = array();

            foreach($method->getParameters() as $p){

                $key = $p->getName();

                $value = null;

                if(array_key_exists($key, $args))
                    $value = $args[$key];
                elseif(array_key_exists('defaults', $endpoint['args']) && array_key_exists($key, $endpoint['args']['defaults']))
                    $value = $endpoint['args']['defaults'][$key];
                elseif($p->isDefaultValueAvailable())
                    $value = $p->getDefaultValue();
                else
                    throw new \Exception("Missing value for parameter '$key'.", 400);

                $params[$p->getPosition()] = $value;

            }

            $result = null;

            /*
             * Enable caching if:
             * * The cache object has been created (ie: the cache library is available)
             * * This endpoint has enabled caching specifically
             * * Global cache is enabled and this endpoint has not disabled caching
             */
            $cache_key = ($this->__rest_cache instanceof \Hazaar\Cache
                && ($endpoint['cache'] === true
                || ($this->__rest_cache_enable_global === true && $endpoint['cache'] !== false))
                ? 'rest_endpoint_' . md5(serialize(array($method, $params, $this->request->getParams()))) : null);

            $response = new \Hazaar\Controller\Response\Json();

            //Try extracting from cache first if caching is enabled
            if($cache_key !== null)
                $result = $this->__rest_cache->get($cache_key);

            //If there is no result yet, execute the endpoint
            if(!$result){

                $result = $method->invokeArgs($this, $params);

                //Save the result if caching is enabled.
                if($cache_key !== null)
                    $this->__rest_cache->set($cache_key, $result);

            }

            $response->populate($result);

            return $response;

        }
        catch(\Exception $e){

            return $this->__exception($e);

        }

    }

    private function __exception(\Exception $e){

        $error = array(
            'ok' => false,
            'error' => array(
                'type' => $e->getCode(),
                'status' => 'REST API ERROR',
                'str' => $e->getMessage(),
                'file' => $e->getFile()
            )
        );

        if(!($code = $e->getCode()) > 0) $code = 500;

        $out = new \Hazaar\Controller\Response\Json($error, $code);

        return $out;

    }

}
