<?php

namespace Hazaar\View\Helper;

/**
 * The HazaarModelView renderer
 *
 * This helper renders a Hazaar\Model\Strict object that has field labels defined.
 */
class Hmv extends \Hazaar\View\Helper {

    public function import(){

        $this->requires('html');

    }

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false){

        $container = $this->html->div();

        return $container->add($this->renderItems($model->export($ignore_empty)))->class('hmvContainer');

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
                    $field[] = $this->html->label($label)->class('hmvSectionLabel');

                $field[] =   $this->html->div($this->renderItems($items))->class('hmvSubItems');

            }else{

                $value = ake($item, 'value');

                if(is_array($value)){

                    $subvalues = array();

                    foreach($value as $valuePart)
                        $subvalues[] = $this->html->div($this->renderValue($valuePart));

                    $field = array(
                       $this->html->label($label)->class('hmvItemLabel'),
                       $this->html->div($subvalues)->class('hmvItemValue')
                   );

                }else{

                    $field = array(
                        $this->html->label($label)->class('hmvItemLabel'),
                        $this->html->div($this->renderValue($value))->class('hmvItemValue')
                    );

                }

            }

            $out[] = $this->html->div($field)->data('name', $key)->class('hmvItem');

        }

        return $out;

    }

    private function renderValue($value){

        if($value instanceof \Hazaar\Http\Uri)
            return $this->html->a($value->toString(), $value->toString())->target('_blank');

        return $value;

    }

}