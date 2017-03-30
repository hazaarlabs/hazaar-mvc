<?php

namespace Hazaar\Console;

abstract class Module extends \Hazaar\Controller\Action {

    private $handler;

    public $view_path;

    public $notices = array();

    final function __construct($name, $path, $application, Handler $handler){

        $this->handler = $handler;

        $this->view_path = $path;

        parent::__construct($name, $application, false);

        $this->view->layout('@console/layout');

    }

    public function __initialize($request){

        $this->view->addHelper('hazaar', array('base_url' => $this->application->url('hazaar/console')));

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

        $this->view->addHelper('extra');

        $this->view->link($this->application->url('hazaar/file/console/layout.css'));

        $this->view->navitems = $this->handler->getNavItems();

        $this->view->notices = array();

    }

    public function init(){

        return true;

    }

    public function addMenuGroup($name, $label, $icon = null, $method = null){

        $this->handler->addMenuGroup($this, $name, $label, $icon, $method);

    }

    protected function addMenuItem($group, $label, $method = null, $icon = null, $suffix = null){

        $this->handler->addMenuItem($this, $group, $label, $method, $icon, $suffix);

    }

    public function url($action = null, $params = array()){

        return $this->application->url('hazaar/console', $action, $params);

    }

    public function file(){

        $file = new \Hazaar\Controller\Response\File($this->view_path . DIRECTORY_SEPARATOR . $this->request->getPath());

        return $file;

    }

    public function notice($msg, $icon = 'bell', $class = null){

        $this->view->notices[] = array(
            'msg' => $msg,
            'class' => $class,
            'icon' => $icon
        );

    }

}