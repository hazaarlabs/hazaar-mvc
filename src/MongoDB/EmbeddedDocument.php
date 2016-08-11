<?php
/**
 * @file        Hazaar/Db/MongoDB/EmbeddedDocument.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\MongoDB;

class EmbeddedDocument implements \ArrayAccess, \Countable, \Iterator {

    /**
     * Holds the original child objects and values
     */
    protected $values = array();

    /**
     * Holds any new children that overwrite values
     */
    protected $new = array();

    /**
     * Holds any new non-child values that have changed
     */
    protected $changes = array();

    /**
     * Holds any non-child vlaues that have been removed
     */
    protected $removes = array();

    /**
     * Internal database cursor
     *
     * @internal
     */
    private $cursor;

    /**
     * @detail      Constructor for a new EmbeddedDocument object.  Optionally takes an argument of an array
     *              to use as values to populate the EmbeddedDocument with.
     *
     * @since       1.0.0
     *
     * @param       Array $values Array of values to populate the document with.
     */
    function __construct($values = array()) {

        if(\Hazaar\Map::is_array($values) && ! is_array($values)) {
            {

                if(method_exists($values, 'toArray')) {

                    $array = $values->toArray();

                    $values = $array;

                } else {

                    throw new Exception\BadValueContainer();

                }
            }

        }

        $this->populate($values);

    }

    /**
     * @detail      Populate sets up the array with initial values.
     *              * This can be used to construct the initial array after it has been instatiated.
     *              * It can also be used to reset an array with different values
     *
     * @since       1.0.0
     *
     * @param       Array $values An array of values to populate the document with
     */
    public function populate(array $values) {

        $this->values = array();

        $this->new = array();

        $this->changes = array();

        $this->removes = array();

        foreach($values as $key => $value) {

            if(\Hazaar\Map::is_array($value)) {

                $this->values[$key] = new EmbeddedDocument($value);

            } else {

                $this->filterIn($value);

                $this->values[$key] = $value;

            }

        }

    }

    /**
     * @detail      __tostring fix - This fixes a bug where you get a value that doesn't exist.  When you do this it
     *              returns an empty Hazaar\\Db\\MongoDB\\EmbeddedDocument object, ready for work.  This is because you may want to
     *              use it as an array so we pass it back to keep usage consistent.  However, if it's SUPPOSED to be a
     *              string, when you use it, it will error without this method, so we just return an empty string.
     *
     * @since       1.0.0
     */
    public function __tostring() {

        return '';

    }

    /**
     * @detail      Input filter applied to child object as they are inserted into the document.  This is used to convert some Hazaar specific
     *              objects into MongoDB object types.  For example, converting an Hazaar\Date object, which stores Timezone data inside it
     *              into a an array with two elements:
     *              * datetime - which is a MongoDate object with the actual timestamp value
     *              * timezone - which is the timezone for the timestamp.
     *
     * @since       1.0.0
     *
     * @param       mixed $value The element to convert before adding
     */
    private function filterIn(&$value) {

        if($value instanceof \Hazaar\Date) {

            $tz = $value->getTimezone();

            $value = array(
                'datetime' => new \MongoDate($value->sec(), $value->usec()),
                'timezone' => $tz->getName()
            );

        }

    }

    /**
     * @detail      Output filter applied to child objects as they are read from the document.  This is to undo any conversions made
     *              to objects with EmbeddedDocument::FilterIn().
     *
     * @since       1.0.0
     *
     * @param       mixed $value The element to check and possibly convert.
     */
    private function filterOut(&$value) {

        if((is_array($value) || $value instanceof EmbeddedDocument) && isset($value['datetime']) && isset($value['timezone'])) {

            if($value['datetime'] instanceof \MongoDate) {

                $sec = $value['datetime']->sec;

                $usec = $value['datetime']->usec;

                $value = new \Hazaar\Date('@' . $sec . '.' . $usec, $value['timezone']);

            } else {

                throw new Exception\BadDateValue();

            }

        }

    }

    /**
     * @detail      Method to get the current value of an element from the document.
     *
     * @since       1.0.0
     *
     * @param       mixed   $key         The element key
     *
     * @param       boolean $auto_create If the element does not exist, automatically create a new EmbeddedDocument with
     *                                   the requested key.  Doing this allows sub-elements to be created and modified on the fly more conveniently. Default: true.
     *
     * @return      mixed The value of the element at the key position
     */
    protected function & get($key, $auto_create = true) {

        $value = null;

        if(array_key_exists($key, $this->changes)) {

            $value = $this->changes[$key];

        } elseif(! array_key_exists($key, $this->removes)) {

            if(array_key_exists($key, $this->new)) {

                $value = $this->new[$key];

            } elseif(array_key_exists($key, $this->values)) {

                $value = $this->values[$key];

            }

        }

        if($auto_create && ! isset($value)) {

            $value = new EmbeddedDocument();

            $this->new[$key] = $value;

        } else {

            $this->filterOut($value);

        }

        return $value;

    }

    /**
     * @detail      Magic method to get a child element.
     */
    public function & __get($key) {

        return $this->get($key);

    }

    /**
     * @detail      ArrayAccess method to get a child element.
     */
    public function & offsetGet($offset) {

        return $this->get($offset);

    }

    /**
     * @detail      Return all values of the current document.
     */
    public function getValues() {

        return array_merge($this->values, $this->new, $this->changes);

    }

    /*
     * Setter Methods
     */
    protected function set($key, $value) {

        $this->filterIn($value);

        if(\Hazaar\Map::is_array($value)) {

            $new = new EmbeddedDocument($value);

            if($key === null) {

                array_push($this->new, $new);

            } else {

                $this->new[$key] = $new;

            }

        } else {

            /*
             * Check if we are actually updating anything so we don't unnecessarily make changes
             */
            if($exists = array_key_exists($key, $this->values)) {

                $current = $this->get($key, false);

                if((string)$current == (string)$value)
                    return null;

            }

            if($key === null) {

                array_push($this->new, $value);

            } else {

                if($exists) {

                    $this->changes[$key] = $value;

                } else {

                    $this->new[$key] = $value;

                }

            }

        }

    }

    public function __set($key, $value) {

        $this->set($key, $value);

    }

    public function offsetSet($offset, $value) {

        $this->set($offset, $value);

    }

    public function extend($values, $whitelist = array()) {

        if(! is_array($whitelist))
            $whitelist = array();

        if(\Hazaar\Map::is_array($values)) {

            foreach($values as $key => $value) {

                if(count($whitelist) > 0 && ! in_array($key, $whitelist))
                    continue;

                if($this->values[$key] instanceof EmbeddedDocument && \Hazaar\Map::is_array($value)) {

                    $this->values[$key]->extend($value);

                } else {

                    $this->set($key, $value);

                }

            }

        }

    }

    /*
     * Exists and Unset Methods
     */

    public function has($key) {

        if(! $this->values)
            return false;

        return ((array_key_exists($key, $this->changes) || array_key_exists($key, $this->new) || array_key_exists($key, $this->values)) && ! array_key_exists($key, $this->removes));

    }

    public function isNull($key) {

        if(! $this->has($key))
            return true;

        return ($this->get($key) == null);

    }

    public function offsetExists($offset) {

        return $this->has($offset);

    }

    public function __unset($key) {

        $this->offsetUnset($key);

    }

    public function offsetUnset($offset) {

        if(array_key_exists($offset, $this->values)) {

            $this->removes[$offset] = true;

        }

        if(array_key_exists($offset, $this->new)) {

            unset($this->new[$offset]);

        }

        if(array_key_exists($offset, $this->changes)) {

            unset($this->changes[$offset]);

        }

    }

    /*
     * Commit any changes to the stored value array
     */
    public function commit() {

        /*
         * First, merge any new children or value changes into the values
         */
        $this->values = array_merge($this->values, $this->new, $this->changes);

        $this->new = array();

        /*
         * Cycle through the values and perform the commit
         */
        foreach($this->values as $key => $child) {

            /*
             * For removes, we remove the values from the values array
             */
            if(array_key_exists($key, $this->removes)) {

                unset($this->values[$key]);

                /*
                 * For child arrays we just tell them to commit too
                 */
            } elseif($child instanceof EmbeddedDocument) {

                if($child->isRemoved()) {

                    unset($this->values[$key]);

                } else {

                    $child->commit();

                }

            }

        }

        /*
         * Reset the changes array.  This will remove everything, including additions to the array.
         */
        $this->changes = array();

        /*
         * Reset any removals as they should now be committed
         */
        $this->removes = array();

    }

    public function reset() {

        $this->changes = array();

        foreach($this->values as $value) {

            if($value instanceof EmbeddedDocument) {

                $value->reset();

            }

        }

    }

    public function hasChanges() {

        return (count($this->changes) > 0);

    }

    public function getChanges() {

        return $changes = $this->changes;

    }

    public function hasRemoves() {

        /*
         * Return true if this array has removes
         */
        if(count($this->removes) > 0)
            return true;

        /*
         * Otherwise search children for any removes
         *
         * This will identify children that are requesting to be removed, or children that have their own children being removed
         */
        foreach($this->values as $value) {

            if($value instanceof EmbeddedDocument) {

                if($value->isRemoved())
                    return true;

                if($value->hasRemoves())
                    return true;

            }

        }

        /*
         * No removes detected
         */

        return false;

    }

    public function isRemoved() {

        return ($this->values === false);

    }

    public function getRemoves() {

        $removes = $this->removes;

        /*
         * Get any removes from children
         */
        foreach($this->values as $key => $value) {

            if(array_key_exists($key, $removes))
                continue;

            if($value instanceof EmbeddedDocument) {

                if($value->isRemoved()) {

                    $removes[$key] = true;

                } elseif($value->hasRemoves()) {

                    $removes[$key] = $value->getRemoves();

                }

            }

        }

        return $removes;

    }

    public function hasNew() {

        return (count($this->new) > 0);

    }

    public function getNew() {

        return $this->new;

    }

    /*
     * Countable Interface Methods
     */

    public function count($committed_only = false) {

        if($committed_only === true) {

            return count($this->values);

        }

        return count(array_diff_key(array_merge($this->values, $this->new, $this->changes), $this->removes));

    }

    /*
     * Iterator Interface Methods
     */
    public function current() {

        $value = current($this->cursor);

        $this->filterOut($value);

        return $value;

    }

    public function key() {

        return key($this->cursor);

    }

    public function next() {

        return next($this->cursor);

    }

    public function rewind() {

        $this->cursor = array_diff_key(array_merge($this->values, $this->new, $this->changes), $this->removes);

        reset($this->cursor);

    }

    public function valid() {

        return array_key_exists(key($this->cursor), $this->cursor);

    }

    public function toArray($ignore_changes = false) {

        $array = array();

        /*
         * Ignore changes gives a 'snapshot' of the current state of stored data, ignoring any new values, removed values or changed values.
         */
        $values = ($ignore_changes ? $this->values : array_merge($this->values, $this->new));

        if(count($values) < 1)
            return $array;

        foreach($values as $key => $value) {

            if($value instanceof EmbeddedDocument) {

                if($value->has('datetime') && $value->has('timezone')) {

                    $this->filterOut($value);

                    $array[$key] = $value;

                } else {

                    if($value->count() > 0) {

                        $array[$key] = $value->toArray();

                    }

                }

            } elseif($ignore_changes || (! $ignore_changes && ! array_key_exists($key, $this->removes))) {

                $array[$key] = $value;

            }

        }

        if(! $ignore_changes) {

            $array = array_merge($array, $this->changes);

        }

        return $array;

    }

    public function & findOne($criteria) {

        if($criteria instanceof \MongoId)
            $criteria = array('_id' => $criteria);

        if(! is_array($criteria))
            $criteria = array('_id' => new \MongoId($criteria));

        foreach($this as $child) {

            foreach($criteria as $key => $value) {

                if(! isset($child[$key]))
                    continue 2;

                if($child[$key] != $value)
                    continue 2;

            }

            return $child;

        }

        return null;

    }

    public function find($criteria) {

        if($criteria instanceof \MongoId)
            $criteria = array('_id' => $criteria);

        if(! is_array($criteria))
            $criteria = array('_id' => new \MongoId($criteria));

        $children = new \Hazaar\Map();

        foreach($this as $id => $child) {

            foreach($criteria as $key => $value) {

                if(! isset($child[$key]))
                    continue 2;

                if($child[$key] != $value)
                    continue 2;

            }

            $children[$id] = $child;

        }

        return $children;

    }

    public function modify($values) {

        foreach($values as $key => $value) {

            $this[$key] = $value;

        }

    }

    public function delete() {

        $this->values = false;

    }

    public function remove($criteria) {

        if($criteria instanceof \MongoId)
            $criteria = array('_id' => $criteria);

        if(! is_array($criteria))
            $criteria = array('_id' => new \MongoId($criteria));

        foreach($this as $id => $child) {

            foreach($criteria as $key => $value) {

                if(! isset($child[$key]))
                    continue 2;

                if($child[$key] != $value)
                    continue 2;

            }

            $this->offsetUnset($id);

            return true;

        }

        return false;

    }

    public function sum($criteria = array(), $fields = array(), $recursive = false) {

        if($criteria instanceof \MongoId)
            $criteria = array('_id' => $criteria);

        if(! empty($criteria) && ! is_array($criteria))
            $criteria = array('_id' => new \MongoId($criteria));

        if($fields && ! is_array($fields))
            $fields = array($fields);

        $sum = 0;

        foreach($this as $id => $child) {

            if($child instanceof EmbeddedDocument) {

                if($recursive) {

                    $sum += $child->sum($criteria, $fields, $recursive);

                } else {

                    continue;

                }

            } else {

                if($criteria !== null && count($child) > 0) {

                    foreach($criteria as $key => $value) {

                        if(! isset($child[$key]))
                            continue 2;

                        if($child[$key] != $value)
                            continue 2;

                    }
                }

                if(! is_array($fields) || count($fields) == 0 || in_array($id, $fields))
                    $sum += (float)$child;

            }

        }

        return $sum;

    }

    public function sumAll($fields, $combine = true) {

        if(! is_array($fields))
            $fields = array($fields);

        $sum = ($combine ? 0 : array());

        foreach($this as $child) {

            if($child instanceof EmbeddedDocument) {

                foreach($fields as $field) {

                    if($combine) {

                        $sum += $child[$field];

                    } else {

                        $sum[$field] += $child[$field];

                    }

                }

            }

        }

        return $sum;

    }

}


