<?php

namespace Hazaar\Cache\Backend;

class Memcached extends \Hazaar\Cache\Backend {

    private   $memcached;

    private   $namespace;

    protected $weight = 2;

    static public function available(){

        $modules = get_loaded_extensions();

        return in_array('memcached', $modules);

    }

    public function init($namespace) {

        $this->namespace = $namespace;

        $this->configure(array(
                             'server'          => 'localhost',
                             'port'            => 11211,
                             'use_compression' => FALSE
                         ));

        $this->addCapabilities('store_objects');

        $this->memcached = new \Memcached($this->options->read('persistent_id', NULL));

        $this->memcached->addServer($this->options->server, $this->options->port);

        $this->memcached->setOption(\Memcached::OPT_COMPRESSION, $this->options->use_compression);

    }

    private function key($key) {

        return $this->namespace . '::' . $key;

    }

    public function has($key) {

        $this->memcached->get($this->key($key));

        $result = $this->memcached->getResultCode();

        return ! ($result == \Memcached::RES_NOTFOUND);

    }

    public function get($key) {

        return $this->memcached->get($this->key($key));

    }

    public function set($key, $value, $timeout = NULL) {

        return $this->memcached->set($this->key($key), $value, $timeout);

    }

    public function remove($key) {

        $this->memcached->delete($this->key($key));

    }

    public function clear() {

        $this->memcached->flush();

    }

}