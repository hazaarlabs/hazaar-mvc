<?php

namespace Hazaar\View\Helper;

/**
 * The HazaarModelView renderer
 *
 * This helper renders a Hazaar\Model\Strict object that has field labels defined.
 */
class Hmv extends \Hazaar\View\Helper {

    public $container_class = 'hmvContainer';

    public $section_class = 'hmvSectionLabel';

    public $sectionitems_class = 'hmvSubItems';

    public $item_class = 'hmvItem';

    public $label_class = 'hmvItemLabel';

    public $value_class = 'hmvItemValue';

    public function import(){

        $this->requires('html');

    }

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false){

        $container = $this->html->div();

        return $container->add($this->renderItems($model->export($ignore_empty)))->class($this->container_class);

    }

    private function renderItems($items){

        if(!is_array($items))
            return null;

        $out = array();

        foreach($items as $key => $item){

            $label = ake($item, 'label');

            if($items = ake($item, 'items')){

                $field = array();

                if($label)
                    $field[] = $this->html->div($this->html->label($label))->class($this->section_class);

                $field[] =   $this->html->div($this->renderItems($items))->class($this->sectionitems_class);

            }else{

                $value = ake($item, 'value');

                if(is_array($value)){

                    $subvalues = array();

                    foreach($value as $valuePart)
                        $subvalues[] = $this->html->div($this->renderValue($valuePart));

                    $field = array(
                       $this->html->div($this->html->label($label))->class($this->label_class),
                       $this->html->div($subvalues)->class($this->value_class)
                   );

                }else{

                    $field = array(
                        $this->html->div($this->html->label($label))->class($this->label_class),
                        $this->html->div($this->renderValue($value))->class($this->value_class)
                    );

                }

            }

            $out[] = $this->html->div($field)->data('name', $key)->class($this->item_class);

        }

        return $out;

    }

    private function renderValue($value){

        if($value instanceof \Hazaar\Http\Uri)
            return $this->html->a($value->toString(), $value->toString())->target('_blank');

        return $value;

    }

}