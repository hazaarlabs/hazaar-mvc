<?php

namespace Hazaar\Console;

class System extends Module {

    public function init(){

        $this->addMenuGroup('System', 'wrench');

    }

    public function index($request){

        $this->view('phpinfo');

        $this->view->link('css/phpinfo.css');

    }

}