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
     */
    public function __default($controller, $action){

        $this->model->loadModules($this->application);

        return $this->model->exec($this, $this->request);

    }

}
