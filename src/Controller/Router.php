<?php

namespace Hazaar\Controller;

class Router extends \Hazaar\Controller {

    private $moduleName;

    private $module;

    private $className;

    public $use_metrics = false;

    public function __initialize(\Hazaar\Application\Request $request){

        parent::__initialize($request);
        
        if(!($path = trim($request->getPath(), '/')))
            return $this->redirect($this->url('console'));

        $parts = explode('/', $path);

        //If the request has no action, redirect to the console sub-controller
        if(!($this->moduleName = array_shift($parts)))
            throw new \Hazaar\Exception('Hazaar router controller failure!');

        $this->className = '\Hazaar\\' . ucfirst($this->moduleName) . '\Controller';

        if(!class_exists($this->className))
            throw new \Hazaar\Exception("Module '{$this->moduleName}' not found!", 404);

        $path = $this->getSupportPath($this->className);

        if(count($parts) > 0 && $parts[0] === 'file'){

            array_shift($parts);

            if(!$path)
                throw new \Hazaar\Exception("Module {$this->moduleName} does not have a support path!", 405);

            $this->module = new \Hazaar\File\Controller($this->moduleName, $this->application, false);

            $this->module->setPath($path);

        }else{

            $locale = $this->application->config['app']['locale'];

            $timezone = $this->application->config['app']['timezone'];

            //Reset the application view configuration to defaults so that we don't use any loaded view options
            $this->application->config->view = [];

            if(defined('RUNTIME_PATH'))
                $this->application->config->app['runtimepath'] = RUNTIME_PATH;

            $this->application->config['app']->extend(array('locale' => $locale, 'timezone' => $timezone));

            $this->module = new $this->className($this->moduleName, $this->application, false);

            if($path)
                \Hazaar\Loader::getInstance()->addSearchPath(FILE_PATH_SUPPORT, $path);

        }

        $request->setPath(implode('/', $parts));

        if(!$this->module instanceof \Hazaar\Controller)
            throw new \Hazaar\Exception('Bad module controller!');

        $this->module->base_path ='hazaar';

        return $this->module->__initialize($request);

    }

    public function __run(){

        return $this->module->__run();

    }

    private function getSupportPath($className = null){

        if(!$className)
            $className = $this->className;

        $reflect = new \ReflectionClass($className);

        $path = dirname($reflect->getFileName());

        while(!file_exists($path . DIRECTORY_SEPARATOR . 'composer.json'))
            $path = dirname($path);

        $libs_path = $path . DIRECTORY_SEPARATOR . 'libs';

        if(file_exists($libs_path))
            return $libs_path;

        return false;

    }

}
