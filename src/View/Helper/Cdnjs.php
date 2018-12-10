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

    private $cache_local = false;

    private $libraries = array();

    static private $cache;

    static private $mutex;

    public function import() {

        if(!self::$cache instanceof \Hazaar\Btree)
            self::$cache = new \Hazaar\Btree(\Hazaar\Application::getInstance()->runtimePath('cdnjs.db'));

    }

    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $this->cache_local = ake($args, 'cache_local', false);

        if($libs = ake($args, 'libs')){

            foreach($libs as $lib){

                if(!is_array($lib))
                    $lib = array('name' => $lib);

                if(!array_key_exists('name', $lib))
                    continue;

                $this->load(ake($lib, 'name'), ake($lib, 'version'), ake($lib, 'files'), ake($lib, 'priority'));

            }

        }

    }

    private function lock(){

        if(Cdnjs::$mutex)
            return false;

        $lock_file = \Hazaar\Application::getInstance()->runtimePath('cdnjs.lck');

        //hack to get this instance to block until other instances have finished using a MUTEX file.
        Cdnjs::$mutex = fopen($lock_file, 'w');

        flock(Cdnjs::$mutex, LOCK_EX);

        return true;

    }

    private function unlock(){

        if(!Cdnjs::$mutex)
            return;

        flock(Cdnjs::$mutex, LOCK_UN);

        fclose(Cdnjs::$mutex);

        Cdnjs::$mutex = null;

    }

    public function __destruct(){

        $this->unlock();

    }

    public function run($view) {

        $view->setImportPriority(100);

        uasort($this->libraries, function($a, $b){
            if ($a['priority'] == $b['priority'])
                return 0;
            return ($a['priority'] > $b['priority']) ? -1 : 1;
        });

        foreach($this->libraries as $name => &$info){

            if(!array_key_exists('load', $info))
                continue;

            foreach($info['load'] as &$file){

                if($this->cache_local){

                    //Create the directory here so that we know later that it is OK to load this library
                    \Hazaar\Application::getInstance()->runtimePath('cdnjs' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $info['version'], true);

                    $url = $this->application->url('hazaar', 'view/helper/cdnjs/lib/' . $name . '/' . $info['version'] . '/' . $file)->encode();

                }else{

                    $url =  $this->url($name, $info['version'], $file);

                }

                if(strtolower(substr($file, -3)) == '.js')
                    $view->requires($url);
                else
                    $view->link($url);

            }

        }

    }

    /**
     * Load a library hosted on CDNJS
     *
     * @param mixed $name       The name of the library to load
     * @param mixed $version    Optionally specify the version to load.  If not specified the latest
     *                          available version will be used.
     * @param mixed $files      Optionally define the files to load.  If not specified, CDNJS profides
     *                          the name of the file to load.  This is restricted to a single file and
     *                          is not always accurate, hence the option to specify the files.
     * @param mixed $priority   Import priority.  The higher this number to soon things will be loaded
     *                          compared to other libraries being loaded.
     * @throws \Exception
     * @return \Hazaar\Version  Returns a Hazaar\Version object detailing the version of the library
     *                          that was loaded.
     */
    public function load($name, $version = null, $files = null, $priority = 0){

        if(in_array($name, $this->libraries))
            return null;

        $info = null;

        //Load library info and retry at most once.  This will simply trigger a forced load from CDNjs on the second attempt.
        for($i = 0; $i < 2; $i++){

            if(!($library_info = $this->getLibraryInfo($name, ($i > 0))))
                return null;

            if(!array_key_exists('assets', $library_info))
                throw new \Exception('CDNJS: Package info for ' . $name . ' does not contain any assets!');

            if($version === null)
                $version = $library_info['version'];

            foreach($library_info['assets'] as $assets){

                if($assets['version'] != $version)
                    continue;

                $info = $assets;

                $info['default'] = $library_info['filename'];

                break 2;

            }

        }

        if(!is_array($info) > 0)
            throw new \Exception('CDNJS: Version ' . $version . ' is not available in package ' . $name);

        $info['priority'] = $priority;

        if($files && is_array($files)){

            $info['load'] = array();

            foreach($files as $file){

                if(in_array($file, $info['files']))
                    $info['load'][] = $file;

            }

        }else $info['load'] = array($info['default']);

        $this->libraries[$name] = $info;

        return new \Hazaar\Version($version);

    }

    public function getLibraryInfo($name, $force_reload = false){

        if($force_reload === false && ($info = self::$cache->get($name)) !== null)
            return $info;

        if($this->lock() === true){

            //Check again if we blocked getting the lock as someone else may have written the info
            if(($info = self::$cache->get($name)) !== null)
                return $info;

        }

        if(!($info = json_decode(file_get_contents('https://api.cdnjs.com/libraries/' . $name), true)))
            throw new \Exception('CDNJS: Error parsing package info!');

        self::$cache->set($name, $info);

        $this->unlock();

        return $info;

    }

    private function url($name, $version, $path){

        return 'https://cdnjs.cloudflare.com/ajax/libs/' . $name . '/' . $version . '/' . ltrim($path, '/');

    }

    public function lib($request){

        $app = \Hazaar\Application::getInstance();

        $app_url = (string)$app->url();

        if(!substr($request->referer(), 0, strlen($app_url)) == $app_url)
            throw new \Exception('You are not allowed to access this resource!', 403);

        list($name, $version, $file) = explode('/', $request->getPath(), 3);

        $path = $app->runtimePath('cdnjs' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $version);

        if(!file_exists($path))
            throw new \Exception('This library is not currently accessible!', 404);

        $cacheFile = new \Hazaar\File($path . DIRECTORY_SEPARATOR . $file);

        if(!$cacheFile->exists()){

            $filePath = $cacheFile->dirname();

            if(!file_exists($filePath))
                mkdir($filePath, 0775, TRUE);

            $url = $this->url($name, $version, $file);

            $cacheFile->set_contents(file_get_contents($url));

            $cacheFile->save();

        }

        $response = new \Hazaar\Controller\Response\File($cacheFile);

        $response->setUnmodified($request->getHeader('If-Modified-Since'));

        return $response;

    }

}
