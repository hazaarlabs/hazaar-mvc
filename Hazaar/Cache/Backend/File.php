<?php

namespace Hazaar\Cache\Backend;

class File extends \Hazaar\Cache\Backend {

    private   $zlib   = FALSE;

    protected $weight = 4;

    private   $namespace;

    function init($namespace) {

        $this->namespace = $namespace;

        $this->configure(array(
                             'cache_dir'   => \Hazaar\Application::getInstance()->runtimePath('cache', TRUE),
                             'file_prefix' => NULL,
                             'use_zlib'    => FALSE
                         ));

        $this->addCapabilities('can_compress', 'store_objects');

        $this->zlib = (in_array('zlib', get_loaded_extensions()));

    }

    private function getAbsoluteFilename($key) {

        return $this->options->cache_dir . '/' . $this->options->file_prefix . md5($this->namespace . '::' . $key);

    }

    private function load($key) {

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

        return $value;

    }

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

        $filename = $this->getAbsoluteFilename($key);

        if($this->zlib && $this->options->use_zlib)
            $value = gzcompress($value, 9);

        $data = array('data' => $value);

        if($timeout > 0)
            $data['expire'] = time() + $timeout;

        file_put_contents($filename, serialize($data));

    }

    public function remove($key) {

        $filename = $this->getAbsoluteFilename(md5($key));

        if(file_exists($filename)) {

            unlink($filename);

            return TRUE;

        }

        return FALSE;

    }

    public function clear() {

        $dir = dir($this->options->cache_dir);

        while(($file = $dir->read()) !== FALSE) {

            if(preg_match('/^' . $this->options->file_prefix . '(.*)/', $file, $matches))
                unlink($this->getAbsoluteFilename($matches[1]));

        }

    }

}