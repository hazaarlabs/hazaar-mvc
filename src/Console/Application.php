<?php

namespace Hazaar\Console;

class Application extends Module {

    public function init(){

        $this->addMenuGroup('app', 'Application', 'bars');

        $this->addMenuItem('app', 'Models', 'models', 'sitemap', 3);

        $this->addMenuItem('app', 'Views', 'views', 'binoculars', 12);

        $this->addMenuItem('app', 'Controllers', 'controllers', 'code-fork', 5);

        $this->addMenuGroup('sys', 'System', 'wrench');

        $this->addMenuItem('sys', 'PHP Info', 'phpinfo');

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

    public function phpinfo($request){

        $this->view('phpinfo');

    }

}