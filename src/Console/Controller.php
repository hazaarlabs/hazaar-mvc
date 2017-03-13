<?php

namespace Hazaar\Console;

class Controller extends \Hazaar\Controller\Action {

    private $passwd = null;

    private $model;

    public function init(){

        $this->model = new Administration();

        if($this->request->getActionName() == 'login')
            return;

        if(!$this->model->authenticated())
            return $this->redirect($this->url('login'));

        $this->view->layout('@console/layout');

        $this->view->addHelper('bootstrap', array('theme' => 'flatly'));

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome', array('version' => '4.7.0'));

        $this->view->requires($this->application->url('file/console/application.js'));

        $this->view->link('console/layout.css');

        $this->view->navitems = $this->model->getNavItems();

    }

    public function login(){

        if($this->request->isPOST()){

            if($this->model->authenticate($this->request->username, $this->request->password))
                $this->redirect($this->url());

            $this->view->msg = 'Login failed';

        }

        $this->layout('@console/login/layout');

        $this->view->link('console/login/main.css');

        $this->view->addHelper('bootstrap');

        $this->view->addHelper('fontawesome');

    }

    public function logout(){

        $this->model->deauth();

        $this->redirect($this->url());

    }


    /**
     * Launch the Hazaar MVC Management Console
     *
     * The Management Console is a virtual desktop that allows the application to be
     * administered in a user friendly environment.
     */
    public function __default($controller, $action){

        $this->view('@console/' . str_replace('_', '/', $action));

    }

    public function snapshot(){

        if(!$this->request->isPOST())
            return false;

        $db = new \Hazaar\DBI\Adapter();

        $result = $db->snapshot($this->request->get('comment'), boolify($this->request->get('testmode', false)));

        return array('ok' => $result, 'log' => $db->getMigrationLog());

    }

    public function migrate(){

        if(!$this->request->isPOST())
            return false;

        $version = $this->request->get('version', 'latest');

        if($version == 'latest')
            $version = null;

        $db = new \Hazaar\DBI\Adapter();

        $result = $db->migrate($version, boolify($this->request->get('testmode', false)));

        return array('ok' => $result, 'log' => $db->getMigrationLog());

    }

    public function syncdata(){

        if(!$this->request->isPOST())
            return false;

        $db = new \Hazaar\DBI\Adapter();

        $result = $db->syncSchemaData();

        return array('ok' => $result, 'log' => $db->getMigrationLog());

    }

}
