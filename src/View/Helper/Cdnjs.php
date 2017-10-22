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

    private $count = 0;

    public function import() {

        if(!self::$cache instanceof \Hazaar\Btree)
            self::$cache = new \Hazaar\Btree(\Hazaar\Application::getInstance()->runtimePath('cdnjs.db'));

    }

    public function init($view, $args = array()) {

        $this->cache_local = ake($args, 'cache_local', false);

        $view->setImportPriority(ake($args, 'priority', 100));

        uasort($this->libraries, function($a, $b){
            if ($a['priority'] == $b['priority'])
                return 0;
            return ($a['priority'] > $b['priority']) ? -1 : 1;
        });

        foreach($this->libraries as $name => &$info){

            if(!array_key_exists('files', $info))
                continue;

            foreach($info['files'] as &$file){

                $url = 'https://cdnjs.cloudflare.com/ajax/libs/' . $name . '/' . $info['version'] . '/' . $file;

                if($this->cache_local){

                    $path = \Hazaar\Application::getInstance()->runtimePath('cdnjs' . DIRECTORY_SEPARATOR . $name, true);

                    $cacheFile = $path . DIRECTORY_SEPARATOR . $file;

                    if(!file_exists($cacheFile)){

                        $filePath = dirname($cacheFile);

                        if(!file_exists($filePath))
                            mkdir($filePath, 0775, TRUE);

                        file_put_contents($cacheFile, file_get_contents($url));

                    }

                    $info = array(
                        'lib' => $name,
                        'file' => $file
                    );

                    $url = $this->application->url('hazaar', 'view/helper/cdnjs/file', $info)->encode();

                }

                if(strtolower(substr($file, -3)) == '.js')
                    $view->requires($url);
                else
                    $view->link($url);


            }

        }

    }

    public function load($name, $version = null, $files = null, $priority = 0){

        if(in_array($name, $this->libraries))
            return false;

        if(!($info = $this->getLibraryInfo($name)))
            return false;

        if(!array_key_exists('assets', $info))
            throw new \Exception('CDNJS: Package info for ' . $name . ' does not contain any assets!');

        $info['priority'] = $priority;

        if($version)
            $info['version'] = $version;

        $version_found = false;

        if($files && is_array($files)){

            foreach($info['assets'] as &$asset){

                if($asset['version'] != $info['version'])
                    continue;

                $version_found = true;

                foreach($files as $file){

                    if(in_array($file, $asset['files']))
                        $info['files'][] = $file;

                }

                break;

            }

        }else{

            $version_found = true;

            $info['files'] = array($info['filename']);

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

    public function file($args){

        $path = \Hazaar\Application::getInstance()->runtimePath('cdnjs' . DIRECTORY_SEPARATOR . ake($args, 'lib'));

        $file = new \Hazaar\File($path . DIRECTORY_SEPARATOR . ake($args, 'file'));

        if(!$file->exists())
            throw new \Exception('File not found!', 404);

        $response = new \Hazaar\Controller\Response\File();
        
        $response->setContent($file->get_contents());

        $response->setContentType($file->mime_content_type());

        return $response;

    }

}
