<?php

namespace Hazaar\View\Helper;

class Hazaar extends \Hazaar\View\Helper {

    function init($view, $args = array()){

        $view->requires($this->application->url('hazaar/js/hazaar.js'));

        $script = 'var hazaar = new HazaarJSHelper("' . $this->application->url() . '");';

        $view->requires($this->html->script($script));

    }

}