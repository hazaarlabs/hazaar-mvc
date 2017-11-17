<?php

namespace Hazaar\Model;

/**
 * Array short summary.
 *
 * Array description.
 *
 * @version 1.0
 * @author JamieCarl
 */
class ChildArray extends DataTypeConverter implements \ArrayAccess, \Iterator {

    private $type;

    private $values = array();

    function __construct($type, $values = array()){

        if(!(is_array($type) || in_array($type, DataTypeConverter::$known_types) || class_exists($type)))
            throw new \Exception('Unknown/Unsupported data type: ' . $type);

        $this->type = $type;

        if(!is_array($values))
            $values = ($values === null) ? array() : array($values);

        foreach($values as $index => $value)
            $this->offsetSet($index, $value);

    }

    public function offsetExists($offset){

        return array_key_exists($offset, $this->values);

    }

    public function offsetGet($offset){

        return $this->values[$offset];

    }

    public function offsetSet($offset, $value){

        if(is_array($this->type))
            $value = new ChildModel($this->type, $value);
        else
            DataTypeConverter::convertType($value, $this->type);

        if($offset === null)
            $this->values[] = $value;
        else
            $this->values[$offset] = $value;

    }

    public function offsetUnset($offset){

        unset($this->values[$offset]);

    }

    public function current(){

        return current($this->values);

    }

    public function next(){

        return next($this->values);

    }

    public function key(){

        return key($this->values);

    }

    public function valid(){

        return (key($this->values) !== null);

    }

    public function rewind(){

        return reset($this->values);

    }

}