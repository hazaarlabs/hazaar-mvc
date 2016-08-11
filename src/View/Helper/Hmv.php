<?php

namespace Hazaar\View\Helper;

/**
 * The HazaarModelView renderer
 *
 * This helper renders a Hazaar\Model\Strict object that has field labels defined.
 */
class Hmv extends \Hazaar\View\Helper {

    public $container_class = 'hmvContainer';

    public $section_tag = 'h1';

    public function import(){

        $this->requires('html');

    }

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false){

        $container = $this->html->table()->class($this->container_class);

        return $container->add($this->renderItems($model->export($ignore_empty)));

    }

    private function renderItems($items){

        if(!is_array($items))
            return null;

        $itemCollection = array();

        foreach($items as $key => $item){

            if($children = ake($item, 'items')){

                $section = $this->html->td($this->html->block($this->section_tag, ake($item, 'label')))->colspan(2);

                $itemCollection[] = $this->html->tr($section);

                $itemCollection = array_merge($itemCollection, $this->renderItems($children));

            }else{

                $label = $this->html->td($this->html->label(ake($item, 'label')));

                $value = $this->html->td(ake($item, 'value'));

                $itemCollection[] = $this->html->tr(array($label, $value))->data('name', $key);

            }

        }

        return $itemCollection;

    }

}