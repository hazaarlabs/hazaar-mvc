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
class ElementCollection implements \ArrayAccess, \Iterator {

    private $elements = array();

    private $original_selector;

    function __construct(&$objects = null, $selector = null, $recursive = false){

        $this->original_selector = $selector;

        if(is_array($objects) && count($objects) > 0)
            $this->elements = $this->match($objects, $this->original_selector,$recursive);

    }

    static private function match(&$objects, $selector = null, $recursive = false){

        //Split on a comma and any amount of adjacent white space
        $parts = preg_split('/\s*,\s*/', $selector);

        $ruleset = array();

        //Compile all the selector rules.
        foreach($parts as $part){

            $rules = array(
                'type' => null,
                'id' => null,
                'classes' => array(),
                'attributes' => array(
                    'match' => array(),
                    'exists' => array()
                ),
                'modifiers' => array()
            );

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

            if(preg_match_all('/\:([\w\-]+)/', $part, $matches)){

                foreach($matches[1] as $match)
                    $rules['modifiers'][] = $match;

            }

            $ruleset[] = $rules;

        }

        return ElementCollection::applyRuleset($objects, $ruleset, $recursive);

    }

    static private function applyRuleset(&$objects, &$ruleset, $recursive = false){

        $collection = array();

        foreach($objects as $object){

            if(!$object instanceof Element)
                continue;

            if($recursive && $object instanceof Block)
                $collection += ElementCollection::applyRuleset($object->get(), $ruleset, $recursive);

            foreach($ruleset as $rules){

                if($rules['type'] && $rules['type'] != $object->getTypeName())
                    continue;

                if($rules['id'] && $rules['id'] != $object->attr('id'))
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

                $collection[] = $object;

            }

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

    public function add($elements){

        if($elements instanceof ElementCollection)
            $elements = $elements->elements;
        elseif(!is_array($elements))
            return $this;

        $collection = new ElementCollection(null, $this->original_selector);

        $collection->elements = $this->elements + $elements;

        return $collection;

    }

    public function children($selector = null){

        $elements = array();

        foreach($this->elements as $element)
            $elements += $element->get();

        return new ElementCollection($elements, $selector);

    }

    public function find($selector = null){

        return new ElementCollection($this->elements, $selector, true);

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

    public function get($index){

        return ake($this->elements, $index);

    }

    public function offsetExists($offset){

        return array_key_exists($offset, $this->elements);

    }

    public function offsetGet($offset){

        if(array_key_exists($offset, $this->elements))
            return $this->elements[$offset];

        return null;

    }

    public function offsetSet($offset, $value){

        if(!$value instanceof Element)
            return;

        $this->elements[$offset] = $value;

    }

    public function offsetUnset($offset){

        if(array_key_exists($offset, $this->elements))
            unset($this->elements[$offset]);

    }

    public function current(){

        return current($this->elements);

    }

    public function next(){

        return next($this->elements);

    }

    public function key(){

        return key($this->elements);

    }

    public function valid(){

        return (current($this->elements));

    }

    public function rewind(){

        return reset($this->elements);

    }

}