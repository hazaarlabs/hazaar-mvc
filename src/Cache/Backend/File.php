<?php

/**
 * @file        Hazaar/Cache/Backend/File.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The file cache backend.
 *
 * @detail This backend will cache data to the filesystem.  It is basically a "works all the time" backend that is available
 * regardless of what modules are installed or server software is available.  It is very handing for using caching on
 * systems where no other cache backends are available.
 *
 * Available config options:
 *
 * * cache_dir - The directory to store cache files in.  Default is to use a 'cache' directory in the application .runtime directory.
 * * file_prefix - This is an optional prefix to apply to the cache files.  Useful if you want to separate your cache files.  Default: no prefix
 * * use_zlib - Enable or disable zlib compression on the cache files.  This can slow things down quite a bit more, but is useful when you
 * are caching very large things.  I wouldn't bother using it under normal circumstances.  Default: false.
 *
 * @since 1.0.0
 */
class File extends \Hazaar\Cache\Backend {

    private   $zlib   = FALSE;

    protected $weight = 4;

    private   $namespace;

    private   $cache_dir;

    private   $timeout_file;

    private   $timeout;

    private   $local = array();

    static public function available(){

        return true;

    }

    function init($namespace) {

        $this->namespace = $namespace;

        if($app = \Hazaar\Application::getInstance())
            $cache_dir = $app->runtimePath('cache', TRUE);
        else
            $cache_dir = sys_get_temp_dir();

        $this->configure(array(
            'cache_dir'   => $cache_dir,
            'file_prefix' => NULL,
            'use_zlib'    => FALSE,
            'encode_fs'   => FALSE
        ));

        $this->cache_dir = $this->options->cache_dir
            . ( ($this->options->file_prefix ) ? DIRECTORY_SEPARATOR . $this->options->file_prefix : null )
            . DIRECTORY_SEPARATOR . ($this->options->encode_fs ? md5($this->namespace) : $this->namespace);

        if(!file_exists($this->cache_dir))
            mkdir($this->cache_dir);

        $this->addCapabilities('store_objects', 'expire_val');

        if(in_array('zlib', get_loaded_extensions())){

            $this->zlib = true;

            $this->addCapabilities('compress');

        }

        if(!$this->options->encode_fs)
            $this->addCapabilities('array');

        //If the lifetime value is greater than 0 then we support namespace timeouts.
        if($this->options->lifetime > 0){

            $this->timeout_file = $this->cache_dir . DIRECTORY_SEPARATOR . '.timeout';

            $this->addCapabilities('expire_ns', 'keepalive');

            //If the timeout file exists, load it and check if we need to drop the namespace.
            $timeout = (file_exists($this->timeout_file) ? intval(file_get_contents($this->timeout_file)) : 0);

            //If the namespace has expired, drop it
            if(time() >= $timeout){

                $this->clear(true);

                $this->timeout = time() + $this->options->lifetime;

            }

        }

    }

    /**
     * Store the namespace timeout in the cache dir timeout file.
     *
     * This should only happen if a keepalive() has been called.
     */
    function __destruct(){

        if($this->timeout_file && $this->timeout > 0)
            file_put_contents($this->timeout_file, $this->timeout);

    }

    private function keepalive(){

        if($this->options->keepalive === true && $this->options->lifetime > 0)
            $this->timeout = time() + $this->options->lifetime;

    }


    private function getAbsoluteFilename($key) {

        $key = str_replace(array('/', '\\'), '_', $key);

        return $this->cache_dir . DIRECTORY_SEPARATOR . ($this->options->encode_fs ? md5($key) : $key);

    }

    /**
     * Load the key value from storage
     *
     * This should only happen once and then it will be stored in memory and only written again when changed.
     *
     * @param mixed $key The value key
     *
     * @throws Exception\NoZlib
     *
     * @return mixed
     */
    private function load($key) {

        if(array_key_exists($key, $this->local))
            return $this->local[$key];

        $value = "\0";

        $filename = $this->getAbsoluteFilename($key);

        if(file_exists($filename)) {

            $cache = file_get_contents($filename);

            $byte = ord(substr($cache, 0, 1));

            if($byte == 120) {

                if(! $this->zlib)
                    throw new Exception\NoZlib($key);

                $cache = gzuncompress($cache);

            }


            $cache = unserialize($cache);

            $expire = ake($cache, 'expire');

            if($expire && $expire < time())
                unlink($filename);
            else
                $value = $cache['data'];

        }

        $this->keepalive();

        return $this->local[$key] = $value;

    }

    /**
     * Check if a value exists
     *
     * @param mixed $key
     * @return boolean
     */
    public function has($key) {

        return ($this->load($key) !== "\0");

    }

    public function get($key) {

        $value = $this->load($key);

        if($value !== "\0")
            return $value;

        return FALSE;

    }

    public function set($key, $value, $timeout = NULL) {

        $this->local[$key] = $value;

        $filename = $this->getAbsoluteFilename($key);

        if($this->zlib && $this->options->use_zlib)
            $value = gzcompress($value, 9);

        $data = array('data' => $value);

        if($timeout > 0)
            $data['expire'] = time() + $timeout;

        $this->keepalive();

        return (file_put_contents($filename, serialize($data)) > 0);

    }

    public function remove($key) {

        $this->keepalive();

        $filename = $this->getAbsoluteFilename($key);

        if(file_exists($filename)) {

            unlink($filename);

            return TRUE;

        }

        return FALSE;

    }

    public function clear() {

        $dir = new \Hazaar\File\Dir($this->cache_dir);

        if(!$dir->exists())
            return false;

        return $dir->empty();

    }

    public function toArray(){

        if($this->options->encode_fs)
            return false;

        $array = array();

        $dir = new \Hazaar\File\Dir($this->cache_dir);

        $keys = $dir->toArray();

        foreach($keys as $key)
            $array[$key] = $this->get($key);

        return $array;

    }

}