<?php

namespace Hazaar\Console;

class Handler {

    private $passwd;

    private $session_key = 'hazaar-console-user';

    private $modules = array();

    private $menus = array();

    private $application;

    public function __construct(){

        $this->passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

        if(!file_exists($this->passwd))
            die('Hazaar admin console is currently disabled!');

        session_start();

    }

    public function authenticated(){

        return ake($_SESSION, $this->session_key);

    }

    public function authenticate($username, $password){

        $users = array();

        $lines = explode("\n", trim(file_get_contents($this->passwd)));

        foreach($lines as $line){

            if(!$line)
                continue;

            list($identity, $userhash) = explode(':', $line);

            $users[$identity] = $userhash;

        }

        $credential = trim(ake($users, $username));

        if(strlen($credential) > 0){

            $hash = '';

            if(substr($credential, 0, 6) == '$apr1$'){

                throw new \Exception('APR1-MD5 encoded passwords are not supported!');

            }elseif(substr($credential, 0, 5) == '{SHA}'){

                $hash = '{SHA}' . base64_encode(sha1($password, TRUE));

            }

            if($hash == $credential){

                $_SESSION[$this->session_key] = $username;

                return true;

            }

        }

        return false;

    }

    public function deauth(){

        session_unset();

    }

    public function loadModules($application){

        $path = LIBRARY_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'console';

        $this->modules['app'] = new Application('app', $path, $application, $this);

        $installed = ROOT_PATH
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer'
            . DIRECTORY_SEPARATOR . 'installed.json';

        if(file_exists($installed)){

            $libraries = json_decode(file_get_contents($installed), true);

            foreach($libraries as $library){

                if(!(($name = substr(ake($library, 'name'), 18))
                    && ake($library, 'type') == 'library'
                    && $consoleClass = ake(ake($library, 'extra'), 'hazaar-console-class')))
                    continue;

                if(!class_exists($consoleClass))
                    continue;

                if(!($path = $this->getSupportPath($consoleClass)))
                    continue;

                $this->modules[$name] = new $consoleClass($name, $path . DIRECTORY_SEPARATOR . 'console', $application, $this);

            }

        }

        ksort($this->modules);

        foreach($this->modules as $module)
            $module->init();

        $this->application = $application;

        return;

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

    public function  moduleExists($name){

        return array_key_exists($name, $this->modules);

    }

    public function exec(\Hazaar\Controller $controller, \Hazaar\Application\Request $request){

        $module_name = $request->getActionName();

        if($module_name == 'index')
            $module_name = 'app';

        if(!$this->moduleExists($module_name))
            throw new \Exception("Console module '$module_name' does not exist!", 404);

        $request->evaluate($request->getRawPath());

        $action = $request->getActionName();

        $module = $this->modules[$module_name];

        if(!method_exists($module, $action))
            throw new \Exception("Method '$action' not found on module '$module_name'", 404);

        if($module->view_path)
            $this->application->loader->setSearchPath(FILE_PATH_VIEW, $module->view_path);

        $module->base_path = 'hazaar/console';

        $module->__initialize($request);

        $module->setRequest($request);

        $response = call_user_func(array($module, $action), $request);

        if(!$response instanceof \Hazaar\Controller\Response){

            if(is_array($response)){

                $response = new \Hazaar\Controller\Response\Json($response);

            }else{

                $response = new \Hazaar\Controller\Response\Html();

                $module->_helper->execAllHelpers($module, $response);

            }

        }

        return $response;

    }

    public function getNavItems(){

        return $this->menus;

    }

    public function addMenuGroup($module, $name, $label, $icon = null){

        if(array_key_exists($name, $this->menus))
            return false;

        $this->menus[$name] = array(
            'label' => $label,
            'icon' => $icon,
            'module' => $module->getName(),
            'items' => array()
        );

        return true;

    }

    public function addMenuItem($module, $group, $label, $method = null, $icon = null, $suffix = null){

        if(!array_key_exists($group, $this->menus))
            return false;

        $this->menus[$group]['items'][] = array(
            'label' => $label,
            'target' => $module->getName() . ($method ? '/' . $method : null),
            'icon' => $icon,
            'suffix' => (is_array($suffix)?$suffix:array($suffix))
        );

        return true;

    }


}