<?php

namespace Hazaar\View\Helper;

class Hazaar extends \Hazaar\View\Helper {

    private $data = array();

    function init($view, $args = array()){

        $view->requires($this->application->url('hazaar/js/hazaar.js'));

        $script = 'var hazaar = new HazaarJSHelper("' . $this->application->url() . '", ' . json_encode($this->data) . ');';

        $view->requires($this->html->script($script));

    }

    public function set($key, $value){

        $this->data[$key] = $value;

    }

    public function get($key, $default = null){

        return ake($this->data, $key, $default);

    }

    public function remove($key){

        if(array_key_exists($key, $this->data))
            unset($this->data[$key]);

    }

}