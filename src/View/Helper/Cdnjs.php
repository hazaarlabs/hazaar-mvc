<?php
/**
 * @file        Hazaar/View/Helper/Cdnjs.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * CDNJS view helper
 *
 * This view helper handles loading any available libraries hosted by CDNJS.
 *
 * @since       2.1.2
 */
class Cdnjs extends \Hazaar\View\Helper {

    private $libraries = array();

    static private $cache;

    private $count = 0;

    public function import() {

        $this->requires('html');

        if(!self::$cache instanceof \Hazaar\Btree)
            self::$cache = new \Hazaar\Btree(\Hazaar\Application::getInstance()->runtimePath('cdnjs.db'));

    }

    public function init($view, $args = array()) {

        foreach($this->libraries as $name => &$info){

            foreach($info['files'] as &$file){

                $url = 'https://cdnjs.cloudflare.com/ajax/libs/' . $name . '/' . $info['version'] . '/' . $file;

                if(strtolower(substr($file, -3)) == '.js')
                    $view->requires($url);
                else
                    $view->link($url);

            }

        }

    }

    public function load($name, $version = null, $additional_files = array()){

        if(in_array($name, $this->libraries))
            return false;

        if(!($info = $this->getLibraryInfo($name)))
            return false;

        if(!array_key_exists('assets', $info))
            throw new \Exception('CDNJS: Package info for ' . $name . ' does not contain any assets!');

        if($version)
            $info['version'] = $version;

        $info['files'] = array($info['filename']);

        if(!is_array($additional_files))
            $additional_files = array($additional_files);

        $version_found = false;

        foreach($info['assets'] as &$asset){

            if($asset['version'] != $info['version'])
                continue;

            $version_found = true;

            foreach($additional_files as $file){

                if(in_array($file, $asset['files']))
                    $info['files'][] = $file;

            }

            break;

        }

        if($version_found === false)
            throw new \Exception('CDNJS: Version ' . $info['version'] . ' is not available in package ' . $name);

        $this->libraries[$name] = $info;

        return true;

    }

    public function getLibraryInfo($name){

        if(($info = self::$cache->get($name)) !== null)
            return $info;

        if(!($data = json_decode(file_get_contents('https://api.cdnjs.com/libraries/' . $name), true)))
            throw new \Exception('CDNJS: Error parsing package info!');

        self::$cache->set($name, $data);

        return $data;

    }

}
