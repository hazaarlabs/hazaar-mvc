<?php

namespace Hazaar\Cache\Backend;

class Apc extends \Hazaar\Cache\Backend {

    protected $weight = 1;

    private   $namespace;

    static public function available(){

        $modules = get_loaded_extensions();

        return in_array('apc', $modules);

    }

    public function init($namespace) {

        $this->namespace = $namespace;

        $this->addCapabilities('store_objects', 'expire', 'array');

    }

    private function key($key) {

        return $this->namespace . '::' . $key;

    }

    public function has($key) {

        return apc_exists($this->key($key));

    }

    public function get($key) {

        return apc_fetch($this->key($key));

    }

    public function set($key, $value, $timeout = NULL) {

        return apc_store($this->key($key), $value, $timeout);

    }

    public function remove($key) {

        apc_delete($this->key($key));

    }

    public function clear() {

        apc_clear_cache('user');

    }

    public function toArray() {

        $iter = new \APCIterator('user');

        $array = array();

        foreach($iter as $key => $value) {

            if(preg_match('/^' . $this->namespace . '::(.*)$/', $key, $matches))
                $array[$matches[1]] = $value['value'];

        }

        return $array;

    }

}