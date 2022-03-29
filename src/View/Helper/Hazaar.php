<?php

namespace Hazaar\View\Helper;

class Hazaar extends \Hazaar\View\Helper {

    private $data = [];

    function init(\Hazaar\View\Layout $view, $args = []){

        if(!$view instanceof \Hazaar\View\Layout)
            return;

        $view->setImportPriority(100);

        if(!($url = ake($args, 'base_url')))
            $url = $this->application->url();

        $view->requires($this->application->url('hazaar/file/js/hazaar.js'));

        $options = [
            'url' => (string)$url,
            'data' => ($this->data ? $this->data : null),
            'rewrite' => boolify($this->application->config->app->get('rewrite')),
            'pathParam' => \Hazaar\Application\Request\Http::$pathParam,
            'queryParam' => \Hazaar\Application\Request\Http::$queryParam
        ];

        $script = 'var hazaar = new HazaarJSHelper(' . json_encode($options) . ');';

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