<?php

namespace Hazaar\Model;

/**
 * Strict model child array
 *
 * The ChildArray class is designed to simple wrap the standard functions of a
 * PHP array with the difference that it is instantiated with a data type that
 * all the values it contains will be converted to.
 *
 * @version 1.0
 * @author JamieCarl
 */
class ChildArray extends DataTypeConverter implements \ArrayAccess, \Iterator, \Countable {

    private $type;

    private $values = array();

    /**
     * ChildArray Constructor
     *
     * The constructor simply takes the data type to use to convert all the items
     * stored in this array.  This is any known data type (int, bool, etc) or even
     * an object class.  We use the same DataTypeConverter class as a strict model.
     *
     * @param mixed $type The data type to convert items to.
     * @param mixed $values The initial array of items to populate the object with.
     * @throws \Exception
     */
    function __construct($type, $values = array()){

        if(!(is_array($type) || in_array($type, DataTypeConverter::$known_types) || class_exists($type)))
            throw new \Exception('Unknown/Unsupported data type: ' . $type);

        $this->type = $type;

        if(!is_array($values))
            $values = ($values === null) ? array() : array($values);

        foreach($values as $index => $value)
            $this->offsetSet($index, $value);

    }

    /**
     * Apply a user supplied function to every member of an array
     *
     * Applies the user-defined callback function to each element of the array array.
     *
     * ChildArray::walk() is not affected by the internal array pointer of array. ChildArray::walk() will
     * walk through the entire array regardless of pointer position.
     *
     * For more information on this method see PHP's array_walk() function.
     *
     * @param mixed $callback   Typically, callback takes on two parameters. The array parameter's value being
     *                          the first, and the key/index second.
     * @param mixed $userdata   If the optional userdata parameter is supplied, it will be passed as the third
     *                          parameter to the callback.
     */
    public function walk($callback, $userdata = NULL){

        foreach($this->values as $key => &$value)
            $callback($value, $key, $userdata);

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

    public function count(){

        return count($this->values);

    }

}