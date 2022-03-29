<?php

namespace Hazaar\Console;

class MenuItem {

    public $label;

    public $target;

    public $icon;

    public $suffix;

    public $items = [];

    function __construct($target, $label, $url = null, $icon = null, $suffix = null){

        $this->label = $label;

        $this->target = (($target instanceof Module) ? $target->getName() : $target) . ($url? '/' . $url:null);

        $this->icon = $icon;

        if($suffix)
            $this->suffix = (is_array($suffix) ? $suffix : [$suffix]);

    }

    public function addMenuItem($label, $url = null, $icon = null, $suffix = null){

        return $this->items[] = new MenuItem($this->target, $label, $url, $icon, $suffix);

    }

    public function render(){


    }

}