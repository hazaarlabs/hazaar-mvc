<?php

/**
 * @file        Hazaar/Cache/Backend/Memcached.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The Memcached cache backend.
 *
 * @detail This backend uses the PHP memcached module to communicate with a memcached server.  It is pretty fast and allows for some
 * basic "clustering" (that isn't really clustering but thats what the memcached guys call it).  It's really just "not-so-fancy" partitioning.
 *
 * * server - The memcached server to connect to, or an array of servers.  Default: localhost.
 * * port - The port to connect to the server on.  Default: 11211
 * * use_compression - Enables compression on the communication link. Default: false
 *
 * @since 2.0.0
 *
 */
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

        $servers = $this->options->server;

        if(!\Hazaar\Map::is_array($servers))
            $servers = array($servers);

        foreach($servers as $server)
            $this->memcached->addServer($server, $this->options->port);

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