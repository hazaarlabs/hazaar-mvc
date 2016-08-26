<?php
/**
 * @file        Hazaar/View/Helper/Datatables.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Datatables view helper
 *
 * @since       2.1.2
 */
class Cdnjs extends \Hazaar\View\Helper {

    private $libraries = array();

    private $cache;

    public function import() {

        $this->requires('html');

        $this->cache = new \Hazaar\Cache('file', array(), 'cdnjs');

    }

    public function init($view, $args = array()){

        foreach($this->libraries as $name => $info){

            $url = 'https://cdnjs.cloudflare.com/ajax/libs/' . $name . '/' . $info['version'] . '/' . $info['filename'];

            $view->requires($url);

        }

    }

    public function load($name, $version = null){

        if(in_array($name, $this->libraries))
            return false;

        if(!($info = $this->getLibraryInfo($name)))
            return false;

        if($version)
            $info['version'] = $version;

        $this->cache->set($name, $info);

        $this->libraries[$name] = $info;

        return true;

    }

    public function getLibraryInfo($name){

        if($this->cache->has($name))
            return $this->cache->get($name);

        if($data = json_decode(file_get_contents('https://api.cdnjs.com/libraries/' . $name), true))
            return $data;

        return null;

    }

}
