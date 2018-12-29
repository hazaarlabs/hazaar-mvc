<?php

namespace Hazaar\Cache;

class Console extends \Hazaar\Console\Module {

    public function init(){

        $this->addMenuGroup('cache', 'Cache');

        $this->addMenuItem('cache', 'Settings', 'index');

    }

    public function index(){

    }

}