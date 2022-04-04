<?php

namespace Hazaar\Console;

abstract class Module extends \Hazaar\Controller\Action {

    protected $request;

    protected $handler;

    public $view_path;

    public $notices = [];

    private $module_info = ['label' => 'Module', 'icon' => 'bars'];

    final function __construct($name, $path){

        $this->view_path = $path;

        parent::__construct($name);

    }

    final public function __configure(Handler $handler){

        $this->handler = $handler;


    }

    public function __initialize(\Hazaar\Application\Request $request){

        if(!$this->handler instanceof Handler)
            throw new \Exception('Module requires a console handler before being initialised!');

        $this->view->layout('@console/layout');

        $this->view->link($this->application->url('hazaar/file/css/popup.css'));

        $this->view->link($this->application->url('hazaar/file/console/css/layout.css'));

        $this->view->addHelper('hazaar', ['base_url' => $this->application->url('hazaar/console')]);

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome');

        $this->view->requires($this->application->url('hazaar/file/js/popup.js'));

        $this->view->requires($this->application->url('hazaar/file/console/js/console.js'));

        $this->view->navitems = $this->handler->getNavItems();

        $this->view->notices = [];

        $this->view->user = [
            'fullname' => $this->handler->getUser(),
            'group' => 'Administrator'
        ];

        $this->request = $request;

        $response = null;

        if(!($this->__action = $request->shiftPath()))
            $this->__action = 'index';

        if(method_exists($this, 'init')) {

            $response = $this->init($request);

            if($response === FALSE)
                throw new \Hazaar\Exception('Failed to initialize action controller! ' . get_class($this) . '::init() returned false!');

        }

        if($request->getPath())
            $this->__actionArgs = explode('/', $request->getPath());

        return $response;

    }

    public function load(){

        return true;

    }

    public function init(){

        return true;

    }

    protected function addMenuItem($label, $icon = null, $suffix = null){

        return $this->handler->addMenuItem($this, $label, null, $icon, $suffix);

    }

    public function url($action = null, $params = []){

        return $this->application->url('hazaar/console', $action, $params);

    }

    public function active(){

        return call_user_func_array([$this->application, 'active'], array_merge(['hazaar', 'console'], func_get_args()));

    }

    public function file(){

        $file = new \Hazaar\Controller\Response\File($this->view_path . DIRECTORY_SEPARATOR . $this->request->getPath());

        return $file;

    }

    public function notice($msg, $icon = 'bell', $class = null){

        $this->view->notices[] = [
            'msg' => $msg,
            'class' => $class,
            'icon' => $icon
        ];

    }

    public function setActiveMenu(MenuItem $item){

        $this->module_info = ['label' => $item->label, 'icon' => $item->icon];

    }

    public function  getActiveMenu(){

        return $this->view->html->div([
            $this->view->html->i()->class('fa fa-' . $this->module_info['icon']),
            ' ',
            $this->module_info['label']
        ]);

    }

}