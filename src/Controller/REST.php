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
 */
abstract class REST extends \Hazaar\Controller {

    private $endpoints = array();

    protected $describe_full = false;

    public function __initialize($request) {

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

                    if(!preg_match('/\(\"([\w\<\>\:\/]+)\"\s*,?\s*(.+)*\)/', $tag, $matches))
                        continue;

                    $version = ($doc->hasTag('version') ? $doc->tag('version')[0] : 1);

                    $route = '/' . ltrim($matches[1], '/');

                    $args = array('method' => array('GET'));

                    if(array_key_exists(2, $matches)){

                        $parts = preg_split('/\s*(,(?![^(\[]*[\)\]]))\s*/', $matches[2]);

                        foreach($parts as $part){

                            list($key, $value) = explode('=', $part, 2);

                            $args[$key] = eval('return ' . $value . ';');

                        }

                    }

                    $this->endpoints[$version][$route] = array(
                        'func' => $method,
                        'doc' => $doc,
                        'args' => $args
                    );

                }

            }

            if(method_exists($this, 'init'))
                $this->init($request);

        }
        catch(\Exception $e){

            return $this->__exception($e);

        }

    }

    public function __run() {

        try{

            $full_path = '/' . $this->request->getRawPath();

            if($full_path == '/')
                return new \Hazaar\Controller\Response\Json($this->__describe_api());

            if(!preg_match('/\/v(\d+)(\/?.*)/', $full_path, $matches))
                throw new \Exception('API version is required', 400);

            $version = intval($matches);

            if(!($path = $matches[2]))
                $path = '/';

            if($path == '/')
                return new \Hazaar\Controller\Response\Json($this->__describe_version($version));

            foreach($this->endpoints[$version] as $route => $endpoint){

                if($this->__match_route($path, $route, $args)){

                    if($this->request->method() == 'OPTIONS'){

                        $response = new \Hazaar\Controller\Response\Json();

                        $response->setHeader('allow', $endpoint['args']['method']);

                        $response->populate($this->__describe_endpoint($route, $endpoint));

                        return $response;

                    }else
                        return $this->__exec_endpoint($endpoint, $args);

                }

            }

            throw new \Exception('Not found', 404);

        }
        catch(\Exception $e){

            return $this->__exception($e);

        }

    }

    private function __describe_api(){

        $api = array();

        foreach(array_keys($this->endpoints) as $version)
            $api[] = array(
                'version' => $version,
                'endpoints' => $this->__describe_version($version)
            );

        return $api;

    }

    private function __describe_version($version){

        if(!array_key_exists($version, $this->endpoints))
            throw new \Exception('No endpoints exist on version ' . $version, 404);

        $api = array();

        foreach($this->endpoints[$version] as $route => $endpoint)
            $this->__describe_endpoint($route, $endpoint, $this->describe_full, $api);

        return $api;

    }

    private function __describe_endpoint($route, $endpoint, $describe_full = false, &$api = null){

        if(!$api) $api = array();

        foreach($endpoint['args']['method'] as $method){

            $info = array(
                'url' => (string)$this->url() . $route,
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

            }

            $api[] = $info;

        }

        return $api;

    }

    private function __match_route($path, $route, &$args = null){

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

        return true;

    }

    private function __exec_endpoint($endpoint, $args){

        try{

            $http_methods = ake($endpoint, 'method', array('GET'));

            if(!in_array($this->request->method(), $http_methods))
                throw new \Exception('Method, ' . $this->request->method() . ', is not allowed!', 403);

            if(!($method = $endpoint['func']) instanceof \ReflectionMethod)
                throw new \Exception('Method is no longer a method!?', 500);

            $params = array();

            foreach($method->getParameters() as $p)
                $params[$p->getPosition()] = ake($args, $p->getName(), ($p->isDefaultValueAvailable() ? $p->getDefaultValue() : null));

            $response = new \Hazaar\Controller\Response\Json();

            $response->populate($method->invokeArgs($this, $params));

            return $response;

        }
        catch(\Exception $e){

            return $this->__exception($e);

        }

    }

    private function __exception(\Exception $e){

        $error = array(
            'code' => $e->getCode(),
            'message' => $e->getMessage()
        );

        $out = new \Hazaar\Controller\Response\Json($error, $e->getCode());

        return $out;

    }

}
