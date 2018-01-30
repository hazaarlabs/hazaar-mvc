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
class ChildArray extends DataTypeConverter implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable {

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

    public function find($criteria = array()){

        foreach($this->values as $value){

            if(!\Hazaar\Map::is_array($value))
                continue;

            if($this->matchItem($value, $criteria))
                return $value;

        }

        return null;

    }

    public function matchItem($item, $criteria){

        foreach($criteria as $key => $value){

            $parts = explode('.', $key);

            foreach($parts as $part){

                if(!isset($item[$part]))
                    return false;

                $item =& $item[$part];

            }

            if($item !== $value)
                return false;

        }

        return true;

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
    public function array_walk($callback, $userdata = NULL){

        foreach($this->values as $key => &$value)
            $callback($value, $key, $userdata);

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
    public function array_walk_recursive($callback, $userdata = NULL){

        foreach($this->values as $key => &$value){

            if($value instanceof Strict || $value instanceof ChildArray)
                $value->array_walk_recursive($callback, $userdata);
            else
                $callback($value, $key, $userdata);

        }

    }


    /**
     * Magic method for calling array_* functions on the ChildArray class.
     *
     * @param mixed $func
     *
     * @param mixed $argv
     *
     * @throws BadMethodCallException
     *
     * @return mixed
     */
    public function __call($func, $argv){

        if (!is_callable($func) || substr($func, 0, 6) !== 'array_')
            throw new \BadMethodCallException(__CLASS__.'->'.$func);

        return call_user_func_array($func, array_merge(array($this->values), $argv));

    }

    /**
     * ChildArray implementation of the implode function
     *
     * @param mixed $glue   The delimeter.  Defaults to an empty string.
     *
     * @return string
     */
    public function implode($glue){

        return implode($glue, $this->values);

    }

    /**
     * ChildArray implementation of the explode function.
     *
     * This operates mostly the same as the built-in PHP explode function except that
     * it requires a type.  The purpose of a ChildArray is to maintain data type of
     * it's elements so a type is required.
     *
     * @param mixed $type   The data type of enforce on this ChildArray.
     *
     * @param mixed $glue   The boundary string.
     *
     * @param mixed $string The input string.
     *
     * @param mixed $limit  If limit is set and positive, the returned array will contain a maximum of limit
     *                      elements with the last element containing the rest of string.
     *
     *                      If the limit parameter is negative, all components except the last -limit are returned.
     *
     *                      If the limit parameter is zero, then this is treated as 1.
     *
     * @return ChildArray
     */
    static function explode($type, $glue, $string, $limit = PHP_INT_MAX){

        return new ChildArray($type, explode($glue, $string, $limit));

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

    public function toArray(){

        return $this->values;

    }

    public function jsonSerialize(){

        return $this->values;

    }

}