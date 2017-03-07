<?php

namespace Hazaar\Controller;

class Router extends \Hazaar\Controller {

    private $moduleName;

    private $module;

    private $className;

    public function __initialize($request){

        if(!($raw_path = trim($request->getRawPath(), '/')))
            $this->redirect($this->url('console'));

        $request->evaluate($raw_path);

        //If the request has no action, redirect to the console sub-controller
        if(!($this->moduleName = $request->getControllerName()))
            throw new \Exception('Hazaar router controller failure!');

        $this->className = '\Hazaar\\' . ucfirst($this->moduleName) . '\Controller';

        if(!class_exists($this->className))
            throw new \Exception("Module '{$this->moduleName}' not found!", 404);

        if($this->request->getActionName() == 'file'){

            if(!($path = $this->getSupportPath($this->className)))
                throw new \Exception("Module {$this->moduleName} does not have a support path!", 405);

            $this->module = new \Hazaar\File\Controller($this->moduleName, $this->application, false);

            $this->module->setPath($path);

            $request->evaluate($request->getRawPath());

        }else{

            $this->module = new $this->className($this->moduleName, $this->application, false);

        }

        if(!$this->module instanceof \Hazaar\Controller)
            throw new \Exception('Bad module controller!');

        $this->module->base_path ='hazaar';

        $this->module->setRequest($request);

        $this->module->__initialize($request);

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
