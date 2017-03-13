<?php

namespace Hazaar\View\Helper;

class Hazaar extends \Hazaar\View\Helper {

    private $data = array();

    function init($view, $args = array()){

        if(!($url = ake($args, 'base_url')))
            $url = $this->application->url();

        $view->requires($this->application->url('hazaar/file/js/hazaar.js'));

        $script = 'var hazaar = new HazaarJSHelper("' . $url . '", ' . json_encode($this->data) . ');';

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

    public function populate($values){

        if(!is_array($values))
            return false;

        $this->data = $values;

        return true;

    }

    public function extend($values){

        if(!is_array($values))
            return false;

        $this->data = array_merge($this->data, $values);

        return true;

    }


}