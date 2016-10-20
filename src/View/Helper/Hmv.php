<?php

namespace Hazaar\View\Helper;

/**
 * The HazaarModelView renderer
 *
 * This helper renders a Hazaar\Model\Strict object that has field labels defined.
 */
class Hmv extends \Hazaar\View\Helper {

    public $container_class = 'hmvContainer';

    public $input_class = 'hmvInput';

    public $section_tag = 'h1';

    public $newitem_class = 'hmvNewItem';

    public function import(){

        $this->requires('html');

    }

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false, $export_all = false, $empty_val = null){

        $container = $this->html->table()->class($this->container_class);

        return $container->add($this->renderItems($model->exportHMV($ignore_empty, $export_all), $empty_val));

    }

    private function renderItems($items, $empty_val = null){

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
                    $childTable->add($this->renderItems($child, $empty_val));

                $itemCollection[] = $this->html->tr(array($section, $this->html->td($childTable)));

            }elseif($children = ake($item, 'items')){

                $section = $this->html->td($this->html->block($this->section_tag, ake($item, 'label')));

                $childTable = $this->html->table()->class($this->container_class);

                $childTable->add($this->renderItems($children, $empty_val));

                $itemCollection[] = $this->html->tr(array($section, $this->html->td($childTable)));

            }else{

                $label = $this->html->td($this->html->label(ake($item, 'label')));

                $value = ake($item, 'value', $empty_val, true);

                if(is_bool($value))
                    $value = ucfirst(strbool($value));

                $itemCollection[] = $this->html->tr(array($label, $this->html->td($value)))->data('name', $key);

            }

        }

        return $itemCollection;

    }

    public function renderEditor(\Hazaar\Model\Strict $model, $export_all = false){

        $container = $this->html->table()->class($this->container_class);

        return $container->add($this->renderInputs($model, null, $export_all));

    }


    private function renderInputs($object, $prefix = null, $export_all = false){

        $tableRows = array();

        $typeMap = array(
            'string' => 'text',
            'bool' => 'checkbox',
            'float' => 'text'
        );

        $hiddenInputs = $this->html->td();

        foreach($object->toArray(true, 0, $export_all) as $key => $item){

            if($prefix)
                $name = $prefix . '[' . $key . ']';
            else
                $name = $key;

            if(!($def = $object->getDefinition($key)))
                $def = array();

            if(ake($def, 'hideInEdit') === true)
                continue;

            if(ake($def, 'hide') === true){

                $hiddenInputs->add($this->html->input('hidden', $name, $item));

                continue;

            }

            if(!($label = ake($def, 'label'))){

                if($export_all)
                    $label = $key;
                else
                    continue;

            }

            if(!$item && $object->isObject($key))
                $item = $object->set($key, array());

            $input = null;

            $edit = ake($def, 'edit', true);

            if(is_callable($edit))
                $edit = $edit($item, $def, $object);

            if($edit == false){

                $labelTD = $this->html->td($this->html->label($label));

                if(is_array($item)){

                    $input = $this->html->div();

                    foreach($item as $subItem)
                        $input->add((string)$subItem);

                }else{

                    $input = (string)$item;

                }

            }elseif($render = ake($def, 'render')){

                $labelTD = $this->html->td($this->html->label($label));

                $input = call_user_func($render, $name, $item, $this->view);

            }elseif($item instanceof \Hazaar\Model\Strict){

                //If the object definition has a data source, use that to create the select
                if($source = ake($def, 'source')){

                    $labelTD = $this->html->td($this->html->label($label));

                    if(!is_callable($source) && method_exists($item, $source))
                        $source = array($item, $source);

                    $data = call_user_func_array($source, ake($def, 'sourceArgs', array()));

                    if(ake($def, 'nulls', true))
                        $data = array('null' => 'Select...') + $data;

                    if($valueKey = ake($def, 'valueKey'))
                        $value = $item->get($valueKey);
                    else
                        $value = (string)$item;

                    $input = $this->html->select($name, $data, $value)->class($this->input_class);


                }else{ //Otherwise, try and render the object as a sub-object.

                    $labelTD = $this->html->td($this->html->block($this->section_tag, $label));

                    $input = $this->html->table()->class($this->container_class);

                    $input->add($this->renderInputs($item, $name));

                }

            }elseif(is_array($item)){

                $labelTD = $this->html->td($this->html->block($this->section_tag, $label));

                if($source = ake($def, 'source')){

                    if($class = ake($def, 'arrayOf')){

                        if(!is_callable($source)){

                            $o = new $class();

                            if(method_exists($o, $source))
                                $source = array($o, $source);

                        }

                        $data = call_user_func_array($source, ake($def, 'sourceArgs', array()));

                        $values = array();

                        if($valueKey = ake($def, 'valueKey')){

                            foreach($item as $i)
                                $values[] = $i->get($valueKey);

                        }

                        $input = $this->html->select($name, $data, $values)->multiple(true)->class($this->input_class);

                    }

                }else{

                    $input = array();

                    $delTR = $this->html->tr(array($this->html->td(), $this->html->td($this->html->span()->class('btnDelItem'))));

                    foreach($item as $index => $i){

                        $table = $this->html->table()->class($this->container_class);

                        $input[] = $table->add($this->renderInputs($i, $name . '[' . $index . ']'), $delTR);

                    }

                    $table = $this->html->table()->class($this->container_class);

                    $input[] = $table->add($this->renderInputs($object->append($key, array()), $name . '[]'), $delTR)->addClass($this->newitem_class);

                    $input[] = $this->html->span()->class('btnNewItem');
                }

            }else{

                $values = null;

                $labelTD = $this->html->td($this->html->label($label));

                if(!array_key_exists('input', $def))
                    $def['input'] = $typeMap[ake($def, 'type', 'string')];

                if($source = ake($def, 'source')){

                    if(is_callable($source)){

                        $values = call_user_func_array($source, ake($def, 'sourceArgs', array()));

                        $def['input'] = 'array';

                    }elseif(is_array($source)){

                        $values = $source;

                        $def['input'] = 'array';

                    }else{

                        $values = $source;

                    }

                }

                switch($type = ake($def, 'input')){
                    case 'array':
                        $input = $this->html->select($name, $values);
                        break;
                    case 'checkbox':
                    case 'text':
                    default:
                        $input = $this->html->input($type, $name, $item)->class($this->input_class);
                        break;

                }


            }

            $tableRows[] = $this->html->tr(array($labelTD, $this->html->td($input)))->data('name', $key);

        }

        if($hiddenInputs->count() > 0)
            $tableRows[] = $this->html->tr($hiddenInputs);

        return $tableRows;

    }

}