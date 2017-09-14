<?php

namespace Hazaar\Html;

/**
 * ElementContainer short summary.
 *
 * ElementContainer description.
 *
 * @version 1.0
 * @author jamiec
 */
class ElementCollection {

    private $elements = array();

    private $original_selector;

    function __construct($objects, $selector = null){

        $this->original_selector = $selector;

        $this->elements = $this->match($objects, $this->original_selector);

    }

    static private function match($objects, $selector = null){

        $collection = array();

        $parts = explode(',', $selector);

        $rules = array(
            'type' => null,
            'id' => null,
            'classes' => array(),
            'attributes' => array(
                'match' => array(),
                'exists' => array()
            )
        );

        //Compile all the selector rules.
        foreach($parts as $part){

            //Match element type references (single)
            if(preg_match('/^(\w+)/', $part, $matches))
                $rules['type'] = strtoupper($matches[1]);

            //Match ID reference (single)
            if(preg_match('/\#([\w-]*)/', $part, $matches))
                $rules['id'] = $matches[1];

            //Match class references (multi)
            if(preg_match_all('/\.([\w-]*)/', $part, $matches)){

                foreach($matches[1] as $match)
                    $rules['classes'][] = $match;

            }

            //Match attribute references (multi)
            if(preg_match_all('/\[(.+)\]/U', $part, $matches)){

                foreach($matches[1] as $match){

                    if(preg_match('/(\w+)=[\'"]?([\s\w]+)[\'"]?/', $match, $pair))
                        $rules['attributes']['match'][$pair[1]] = $pair[2];
                    else
                        $rules['attributes']['exists'][] = $match;

                }

            }

        }

        foreach($objects as $object){

            if(!$object instanceof Element)
                continue;

            if(!($id = $object->attr('id')))
                $id = uniqid();

            if($rules['type'] && $rules['type'] != $object->getTypeName())
                continue;

            if($rules['id'] && $rules['id'] != $id)
                continue;

            if(count($rules['classes']) > 0 && count(array_diff($rules['classes'], explode(' ', $object->attr('class')))) > 0)
                continue;

            if(count($rules['attributes']['exists']) > 0 && count(array_diff($rules['attributes']['exists'], array_keys($object->parameters()->toArray()))) > 0)
                continue;

            if(count($rules['attributes']['match']) > 0){

                foreach($rules['attributes']['match'] as $key => $value){

                    if($object->attr($key) != $value)
                        continue 2;

                }

            }

            $collection[$id] = $object;

        }

        return $collection;

    }

    public function filter($selector){

        foreach($this->elements as $id => $object){

            if(!$this->match($object, $selector))
                unset($this->elements[$id]);

        }

        return $this;

    }

    public function count(){

        return count($this->elements);

    }

    public function __call($method, $args){

        if(is_array($this->elements)){

            foreach($this->elements as $element)
                call_user_func_array(array($element, $method), $args);

        }

        return $this;

    }

    public function __toString(){

        $output = '';

        foreach($this->elements as $element)
            $output .= $element;

        return $output;

    }

}