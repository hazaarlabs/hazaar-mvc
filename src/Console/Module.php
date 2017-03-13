<?php

namespace Hazaar\Console;

abstract class Module extends \Hazaar\Controller\Action {

    private $handler;

    public $view_path;

    final function __construct($name, $path, $application, Handler $handler){

        $this->handler = $handler;

        $this->view_path = $path;

        parent::__construct($name, $application);

    }

    public function __initialize($request){

        $this->view->layout('@console/layout');

        $this->view->addHelper('hazaar', array('base_url' => $this->application->url('hazaar/console')));

        $this->view->addHelper('bootstrap', array('theme' => 'flatly'));

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

        $this->view->link($this->application->url('hazaar/file/console/layout.css'));

        $this->view->navitems = $this->handler->getNavItems();

    }

    public function init(){

        return true;

    }

    public function addMenuGroup($name, $label){

        $this->handler->addMenuGroup($this, $name, $label);

    }

    protected function addMenuItem($group, $label, $method = null){

        $this->handler->addMenuItem($this, $group, $label, $method);

    }

    public function url($action = null, $params = array()){

        return $this->application->url('hazaar/console', $action, $params);

    }

    public function file(){

        $file = new \Hazaar\Controller\Response\File($this->view_path . DIRECTORY_SEPARATOR . $this->request->getPath());

        return $file;

    }

}