<?php

namespace Hazaar\View;

class Controller extends \Hazaar\Controller {

    private $helper;

    private $method;

    private $params;

    public function __initialize(\Hazaar\Application\Request $request){

        if(!($raw_path = trim($request->getRawPath(), '/')))
            throw new \Exception('Not allowed!', 400);

        $request->evaluate($raw_path);

        $action = $request->getActionName();

        $controller = $request->getControllerName();

        switch($controller){
            case 'helper':

                $request->evaluate($request->getRawPath());

                $className = 'Hazaar\\View\\Helper\\' . ucfirst($action);

                if(!class_exists($className))
                    throw new \Exception('Helper class not found!', 404);

                $this->helper = new $className();

                $this->method = $request->getActionName();

                $this->params = array($request);

                break;

            case 'js':
            case 'css':

                $this->helper = new \Hazaar\View\Layout();

                $this->method = 'lib';

                $this->params = array($controller, $request);

                break;

            default:
                throw new \Exception('Method not allowed!', 403);

        }

    }

    public function __run(){

        if(!method_exists($this->helper, $this->method))
            throw new \Exception('Method not found!', 404);

        $response = call_user_func_array(array($this->helper, $this->method), $this->params);

        return $response;

    }

}
