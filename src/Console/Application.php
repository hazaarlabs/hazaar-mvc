<?php

namespace Hazaar\Console;

class Application extends Module {

    public function init(){

        $this->addMenuGroup('app', 'Application');

        $this->addMenuItem('app', 'Overview');

        $this->addMenuItem('app', 'Models', 'models');

        $this->addMenuItem('app', 'Views', 'views');

        $this->addMenuItem('app', 'Controllers', 'controllers');

    }

    public function index($request){

        $this->view('index');

        $this->view->requires('console.js');

    }

    public function models($request){

        $this->view('models');

    }

    public function views($request){

        $this->view('views');

    }

    public function controllers($request){

        $this->view('controllers');

    }

}