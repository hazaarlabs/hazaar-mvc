<?php
/**
 * @file        Hazaar/View/Helper/Cdnjs.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaar.io)
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

    private $libraries = [];

    static private $cache;

    static private $mutex;

    private $lock_file;

    public function import() {

        if(!self::$cache instanceof \Hazaar\Btree)
            self::$cache = new \Hazaar\Btree(\Hazaar\Application::getInstance()->runtimePath('cdnjs.db'));

    }

    public function init(\Hazaar\View\Layout $view, $args = []) {

        $this->cache_local = ake($args, 'cache_local');

        if($libs = ake($args, 'libs')){

            foreach($libs as $lib){

                if(!is_array($lib))
                    $lib = ['name' => $lib];

                if(!array_key_exists('name', $lib))
                    continue;

                $this->load(ake($lib, 'name'), ake($lib, 'version'), ake($lib, 'files'), ake($lib, 'priority'));

            }

        }

    }

    private function lock(){

        if(self::$mutex)
            return false;

        $this->lock_file = \Hazaar\Application::getInstance()->runtimePath('cdnjs.lck');

        //hack to get this instance to block until other instances have finished using a MUTEX file.
        self::$mutex = fopen($this->lock_file, 'w');

        flock(self::$mutex, LOCK_EX);

        return true;

    }

    private function unlock(){

        if(!self::$mutex)
            return;

        flock(self::$mutex, LOCK_UN);

        fclose(self::$mutex);

        self::$mutex = null;

    }

    public function __destruct(){

        $this->unlock();

        if($this->lock_file)
            unlink($this->lock_file);

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

                $url = 'https://cdnjs.cloudflare.com/ajax/libs/' . $name . '/' . $info['version'] . '/' . ltrim($file, '/');

                if(strtolower(substr($file, -3)) == '.js')
                    $view->requires($url, null, $this->cache_local);
                else
                    $view->link($url, null, $this->cache_local);

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
    public function load($name, $requested_version = null, $files = null, $priority = 0){

        if(array_key_exists($name, $this->libraries))
            return null;

        if($name === 'inputmask')
            echo '';

        $version_info = null;

        //Load library info.
        if(!($library_info = $this->getLibraryInfo($name)))
            return null;

        if(!array_key_exists('assets', $library_info))
            throw new \Hazaar\Exception('CDNJS: Package info for ' . $name . ' does not contain any assets!');

        $load_version = ($requested_version === null) ? $library_info['version'] : $requested_version;

        foreach($library_info['assets'] as $assets){

            if($assets['version'] === $load_version){

                $version_info = $assets;

                break;

            }

        }

        if($version_info === null)
            $version_info = $this->getLibraryVersion($name, $load_version);

        $version_info['default'] = $library_info['filename'];

        if(!(is_array($version_info) > 0 
            && array_key_exists('files', $version_info) 
            && count($version_info['files']) > 0)){

            //A version was not requested and the current version has problems, so let's look for a good version
            if($requested_version === null)
                throw new \Hazaar\Exception('CDNJS: No version specified loading library (' . $name . ') but latest version (' . $load_version .') has no assets!  You might need to specify a specific version.');

            throw new \Hazaar\Exception('CDNJS: Version ' . $load_version . ' is not available in package ' . $name);

        }

        $version_info['priority'] = $priority;

        if($files && is_array($files)){

            $version_info['load'] = [];

            foreach($files as $file){

                if(in_array($file, $version_info['files']))
                    $version_info['load'][] = $file;

            }

        }else $version_info['load'] = [$version_info['default']];

        $this->libraries[$name] = $version_info;

        return new \Hazaar\Version($load_version);

    }

    public function getLibraryInfo($name, $force_reload = false){

        if($force_reload === false && ($info = self::$cache->get($name)) !== null)
            return $info;

        if($this->lock() !== true){

            //Check again if we blocked getting the lock as someone else may have written the info
            if(($info = self::$cache->get($name)) !== null)
                return $info;

        }

        if(!($info = json_decode(file_get_contents('https://api.cdnjs.com/libraries/' . $name), true)))
            throw new \Hazaar\Exception('CDNJS: Error parsing package info!');

        self::$cache->set($name, $info);

        $this->unlock();

        return $info;

    }

    public function getLibraryVersion($name, $version, $force_reload = false){

        $cache_key = $name . '-' . $version;

        if($force_reload === false && ($info = self::$cache->get($cache_key)) !== null)
            return $info;

        if($this->lock() !== true){

            //Check again if we blocked getting the lock as someone else may have written the info
            if(($info = self::$cache->get($cache_key)) !== null)
                return $info;

        }

        if(!($content = @file_get_contents('https://api.cdnjs.com/libraries/' . $name . '/' . $version)))
            throw new \Exception("CDNJS: Requested version ($version) of '$name' is not found!");
            
        if(!($info = json_decode($content, true)))
            throw new \Hazaar\Exception('CDNJS: Error parsing package info!');

        self::$cache->set($cache_key, $info);
            
        $this->unlock();

        return $info;

    }

}
