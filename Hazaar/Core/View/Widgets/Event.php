<?php

namespace Hazaar\View\Widgets;

class Event {

    private $name;

    private $script;

    function __construct($name, $script) {
        
        $this->name = $name;

        if(! $script instanceof JavaScript)
            $script = new JavaScript($script, 'event');

        $this->script = $script->anon();

    }
    
    public function name() {

        return $this->name;

    }

    public function script() {

        return $this->script;

    }

}
