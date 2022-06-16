<?php

/**
 * @file        Hazaar/Cache/Backend/Apc.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The APC cache backend.
 *
 * @detail This is a high performance caching backend that uses user cache functions
 * that are part of the APC module.
 *
 * There are no special options required to use this backend.  It only requires that
 * the APC PHP module be installed and operational.
 *
 * @since 1.0.0
 */
class Apc extends \Hazaar\Cache\Backend {

    protected   $weight = 1;

    private     $namespace;

    private     $refresh = [];

    static public function available(){

        //Make sure APCU extension is loaded
        return in_array('apcu', get_loaded_extensions()) 
            //Check that we are either not on the CLI or that APCU CLI is enabled.
            && (\php_sapi_name() !== 'cli' || ini_get('apc.enable_cli') === '1'); 

    }

    public function init($namespace) {

        $this->namespace = $namespace;

        $this->addCapabilities('store_objects', 'expire', 'array', 'expire_ns', 'expire_val', 'keepalive');

    }

    public function close(){

        if(count($this->refresh) === 0)
            return;

        foreach($this->refresh as $key => &$value)
            apcu_store($this->key($key), $value, $this->options->lifetime);

    }

    private function key($key) {

        return $this->namespace . '::' . $key;

    }

    public function has($key) {

        return apcu_exists($this->key($key));

    }

    public function get($key) {

        $result = apcu_fetch($this->key($key));

        if($result && $this->options->keepalive && $this->options->lifetime > 0)
            $this->refresh[$key] = $result;

        return $result;

    }

    public function set($key, $value, $timeout = NULL) {

        if(!$timeout && $this->options->lifetime > 0)
            $timeout = $this->options->lifetime;

        if(array_key_exists($key, $this->refresh))
            unset($this->refresh[$key]);

        return apcu_store($this->key($key), $value, $timeout);

    }

    public function remove($key) {

        if(!apcu_delete($this->key($key)))
            return false;

        if(isset($this->refresh[$key]))
            unset($this->refresh[$key]);

        return true;

    }

    public function clear() {

        return apcu_clear_cache();

    }

    public function toArray() {

        $iter = new \APCUIterator('/^' . $this->namespace . '::/');

        $array = [];

        $pos = strlen($this->namespace) + 2;

        foreach($iter as $ns_key => $value)
            $array[substr($ns_key, $pos)] = $value['value'];

        return $array;

    }

}