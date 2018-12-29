<?php

/**
 * @file        Hazaar/Cache/Backend/Chain.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The cache backend chaining backend.
 *
 * @detail This backend allows other caching backends to be chained together for fault tollerance.  All operations (has,get,set,remove) are
 * performed on ALL backends at once.
 *
 * Configuration options for this backend is an array of 'backend_name' => 'backend_options' pairs.  (see each backend for available options)
 *
 * For example:
 *
 * [ 'file' => [ 'cache_dir' => '/tmp/cache' ], 'memcached' => [ 'server' => 'localhost' ] ]
 *
 * @since 1.0.0
 */
class Chain extends \Hazaar\Cache\Backend {

    private $backends = array();

    private $order    = array();

    static public function available(){

        return true;

    }

    public function init($namespace) {

        foreach($this->options as $backend => $backendOptions) {

            if(is_int($backend)) {
                $backend = $backendOptions;
                $backendOptions = NULL;
            }

            $backend = strtolower($backend);

            $backendClass = '\\Hazaar\\Cache\\Backend\\' . ucfirst($backend);

            $obj = new $backendClass($backendOptions, $namespace);

            $this->backends[$backend] = $obj;

            $this->order[$backend] = $obj->getWeight();

        }

        asort($this->order);

    }

    public function has($key) {

        foreach($this->backends as $backend) {

            if($backend->test($key))
                return TRUE;

        }

        return FALSE;

    }

    public function get($key) {

        $store = array();

        $value = FALSE;

        foreach($this->order as $backend => $weight) {

            if(($value = $this->backends[$backend]->get($key)) === FALSE)
                $store[] = $backend;

            else
                break;

        }

        if($value !== FALSE) {

            foreach($store as $backend)
                $this->backends[$backend]->set($key, $value);

        }

        return $value;

    }

    public function set($key, $value, $timeout = NULL) {

        foreach($this->backends as $backend)
            $backend->set($key, $value, $timeout);

    }

    public function remove($key) {

        foreach($this->backends as $backend)
            $backend->remove($key);

    }

    public function clear() {

        foreach($this->backends as $backend)
            $backend->clear();

    }

    public function setExpireTimeout($timeout) {

        $this->setOption('expire', $timeout);

    }

}