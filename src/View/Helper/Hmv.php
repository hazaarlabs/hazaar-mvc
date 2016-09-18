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

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false, $export_all = false){

        $container = $this->html->table()->class($this->container_class);

        return $container->add($this->renderItems($model->exportHMV($ignore_empty, $export_all)));

    }

    private function renderItems($items){

        if(!is_array($items))
            return null;

        $itemCollection = array();

        foreach($items as $key => $item){

            if($children = ake($item, 'list')){

                $label = $this->html->td($this->html->label(ake($item, 'label')));

                $itemsTD = $this->html->td();

                foreach($children as $child)
                    $itemsTD->add($this->html->div($child));

                $itemCollection[] = $this->html->tr(array($label, $itemsTD));

            }elseif($children = ake($item, 'collection')){

                $section = $this->html->td($this->html->block($this->section_tag, ake($item, 'label')));

                $childTable = $this->html->table()->class($this->container_class);

                foreach($children as $child)
                    $childTable->add($this->renderItems($child));

                $itemCollection[] = $this->html->tr(array($section, $this->html->td($childTable)));

            }elseif($children = ake($item, 'items')){

                $section = $this->html->td($this->html->block($this->section_tag, ake($item, 'label')));

                $childTable = $this->html->table()->class($this->container_class);

                $childTable->add($this->renderItems($children));

                $itemCollection[] = $this->html->tr(array($section, $this->html->td($childTable)));

            }else{

                $label = $this->html->td($this->html->label(ake($item, 'label')));

                $value = ake($item, 'value');

                if(is_bool($value))
                    $value = ucfirst(strbool($value));

                $itemCollection[] = $this->html->tr(array($label, $this->html->td($value)))->data('name', $key);

            }

        }

        return $itemCollection;

    }

}