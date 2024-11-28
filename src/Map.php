<?php

declare(strict_types=1);

/**
 * @file        Hazaar/Map.php
 *
 * @author      Jamie Carl <jamie@hazaar.io>
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaar.io)
 */

namespace Hazaar;

/**
 * Enhanced array access class.
 *
 * The Map class acts similar to a normal PHP Array but extends it's functionality considerably.  You are
 * able to reset a Map to default values, easily extend using another Map or Array, have more simplified
 * access to array key functions, as well as output to various formats such as Array or JSON.
 *
 * ### Example
 *
 * ```php
 * $map = new Hazaar\Map();
 * $map->depth0->depth1->depth2 = ['foo', 'bar'];
 * echo $map->toJson();
 * ```
 *
 * The above example will print the JSON string:
 *
 * ```php
 * { "depth0" : { "depth1" : { "depth2" : [ "foo", "bar" ] } } }
 * ```
 *
 * ## Filters
 *
 * Filters are callback functions or class methods that are executed upon a get/set call.  There are two
 * methods
 * used for applying filters.
 *
 * * Map::addInputFilter() - Executes the filter when the element is added to the Map (set).
 * * Map::addOutputFilter()  - Executes the filter when the element is read from the Map (get).
 *
 * The method executed is passed two arguments, the value and the key, in that order.  The method must
 * return the value that it wants to be used or stored.
 *
 * ### Using Filters
 *
 * Here is an example of using an input filter to convert a Date object into an array of epoch and a
 * timezone field.
 *
 * ```php
 * $callback = function($value, $key){
 *     if(is_a('\Hazaar\Date', $value)){
 *         $value = new Map([
 *             'datetime' => $value->timestamp(),
 *             'timezone' => $value->timezone()
 *         ]);
 *     }
 *     return $value;
 * }
 * $map->addInputFilter($callback, '\Hazaar\Date', true);
 * ```
 *
 * Here is an example of using an output filter to convert an array with two elements back into a Date
 * object.
 *
 * ```php
 * $callback = funcion($value, $key){
 *     if(Map::is_array($value) && isset('datetime', $value) && isset('timezone', $value)){
 *         $value = new \Hazaar\Date($value['datetime'], $value['timezone']);
 *     }
 *     return $value;
 * }
 * $map->addInputFilter($callback, '\Hazaar\Map', true);
 * ```
 *
 * The second parameter to the addInputFilter/addOutputFilter methods is a class condition, meaning that
 * the callback will only be called on objects of that type (uses is_a() internally).
 *
 * The third parameter says that you want the filter to be applied to all child Map elements as well.
 * This
 * is a very powerful feature that will allow type modification of any element at any depth of the Map.
 *
 * @implements  \ArrayAccess<string, mixed>
 * @implements  \Iterator<string, mixed>
 */
class Map implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * Holds the original child objects and values.
     *
     * @var array<mixed>
     */
    protected array $defaults = [];

    /**
     * Holds the active elements.
     *
     * @var array<mixed>
     */
    protected array $elements = [];

    /**
     * The current value for array access returned by Map::each().
     */
    protected mixed $current;

    /**
     * Allows the map to be locked so that it's values are not accidentally changed.
     */
    protected bool $locked = false;

    /**
     * Optional filter definition to modify objects as they are set or get.
     *
     * Filters are an array with the following keys:
     *
     * * callback - The method to execute.  Can be a PHP callback definition or function name.
     * * class    - A class name or array of class names that that the callback will be executed
     * on.  Null means all elements.
     * * recurse  - Whether this filter should be recursively added to new and existing child elements
     *
     * @var array<string, array<array<string, mixed>>>
     */
    private array $filter = [];

    /**
     * The Map constructor sets up the default state of the Map.  You can pass an array or another Map
     * object
     * to use as default values.
     *
     * In the constructor you can also optionally extend the defaults.  This is useful for when you have a
     * default
     * set of values that may or may not exist in the extended array.
     *
     * ### Example
     *
     * ```php
     *   $config = ['enabled' => true];
     *   $map = new Hazaar\Map([
     *     'enabled' => false,
     *     'label' => 'Test Map'
     *   ], $config);
     *
     *   var_dump($map->toArray());
     * ```
     *
     * This will output the following text:
     *
     * ```
     *   array (size=2)
     *     'enabled' => boolean true
     *     'label' => string 'Test Map' (length=8)
     * ```
     *
     * !!! notice
     * If the input arguments are strings then the Map class will try and figure out what kind of string it
     * is and either convert from JSON or unserialize the string.
     *
     * @param mixed                                      $defaults   Default values will set the default state of the Map
     * @param mixed                                      $extend     Extend the default values overwriting existing key values and creating new ones
     * @param array<string, array<array<string, mixed>>> $filter_def Optional filter definition
     */
    public function __construct(mixed $defaults = [], mixed $extend = [], array $filter_def = [])
    {
        // If we get a string, try and convert it from JSON
        if (is_string($defaults)) {
            if ($json = @json_decode($defaults, true)) {
                $defaults = $json;
            } else {
                throw new Exception\UnknownStringArray($defaults);
            }
        } elseif ($defaults instanceof \stdClass) {
            $defaults = get_object_vars($defaults);
        }
        if ($defaults instanceof Map) {
            $filter_def = array_merge($filter_def, $defaults->filter);
        }
        if ($extend instanceof Map) {
            $filter_def = array_merge($filter_def, $extend->filter);
        }
        $this->filter = $filter_def;
        $this->populate($defaults);
        if ($extend) {
            $this->extend($extend);
        }
    }

    /**
     * Magic method to test if an element exists.
     *
     * @return bool true if the element exists, false otherwise
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Magic method to allow -> access to when setting a new kay value.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic method to remove an element.
     */
    public function __unset(string $key): void
    {
        $this->del($key);
    }

    /**
     * Magic method to convert the map to a string.  See Map::toString();.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    public function __sleep(): array
    {
        return ['defaults', 'elements', 'current', 'locked'];
    }

    /**
     * @param null|array<mixed>|Map ...$args
     */
    public static function &_(mixed $array, null|array|Map ...$args): mixed
    {
        if (!(is_array($array) || is_object($array))) {
            return $array;
        }
        $map = $array instanceof Map ? $array : new Map($array);
        foreach ($args as $arg) {
            $map->extend($arg);
        }

        return $map;
    }

    /**
     * Populate sets up the array with initial values.
     * * This can be used to construct the initial array after it has been instatiated.
     * * It can also be used to reset an array with different values.
     *
     * Input filters are also applied at this point so that default elements can also be modified.
     *
     * !!! warning
     * This method will overwrite ALL values currently in the Map.
     *
     * @param array<mixed>|Map $defaults map or Array of values to initialise the Map with
     * @param bool             $erase    If TRUE resets the default values.  If FALSE, then the existing defaults are kept
     *                                   but will be overwritten by any new values or created if they do not already exist.
     *                                   Use this to add new default values after the object has been created.
     */
    public function populate(array|Map $defaults, bool $erase = true): Map
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        if ($erase) {
            $this->defaults = [];
        }
        foreach ($defaults as $key => $value) {
            // Here we want to specifically look for a REAL array so we can convert it to a Map
            if (is_array($value) || $value instanceof \stdClass) {
                $value = new Map($value, null, $this->filter);
            } elseif ($value instanceof Map) {
                $value->applyFilters($this->filter);
            }
            $value = $this->execFilter($key, $value, 'in');
            $this->defaults[$key] = $value;
        }
        if ($erase) {
            $this->elements = $this->defaults;
        }

        return $this;
    }

    /**
     * Merge this Map and new values into a new Map.
     *
     * This is similar to Map::populate() except that the existing values will be removed first and
     * new values will be added and/or overwrite those existing values.
     *
     * @param array<mixed>|\Hazaar\Map $array the array being merged in
     */
    public function merge(array|Map $array): Map
    {
        return new Map($this->toArray(), $array instanceof Map ? $array->toArray() : $array);
    }

    /**
     * Commit any changes to be the new default values.
     *
     * @param bool $recursive recurse through any child Map objects and also commit them
     *
     * @return bool True on success.  False otherwise.
     */
    public function commit($recursive = true): bool
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        if ($recursive) {
            foreach ($this->elements as $key => $value) {
                if ($value instanceof Map) {
                    $value->commit($recursive);
                }
            }
        }
        $this->defaults = $this->elements;

        return true;
    }

    /**
     * Clear all values from the array.
     *
     * It is still possible to reset the array back to it's default state after doing this.
     */
    public function clear(): void
    {
        $this->elements = [];
    }

    /**
     * Check whether the map object is empty or not.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return 0 == count($this->elements);
    }

    /**
     * Reset the Map back to its default values.
     */
    public function reset(bool $recursive = false): bool
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        $this->elements = $this->defaults;
        if (true == $recursive) {
            foreach ($this->elements as $key => $value) {
                if ($value instanceof Map) {
                    if (!$value->reset()) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * The cancel method will flush the default elements so that all elements are considered new or changed.
     */
    public function cancel(bool $recursive = true): bool
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        $this->defaults = [];
        foreach ($this->elements as $elem) {
            if ($elem instanceof Map) {
                $elem->cancel($recursive);
            }
        }

        return true;
    }

    /**
     * Countable interface method.  This method is called when a call to count() is made on this object.
     *
     * @return int the number of elements in this Map
     */
    public function count(bool $ignorenulls = false): int
    {
        if (false == $ignorenulls) {
            return count($this->elements);
        }
        $count = 0;
        foreach ($this->elements as $elem) {
            if (null !== $elem) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Test if an element exists in the Map object.
     *
     * @return bool true if the element exists, false otherwise
     */
    public function has(string $key): bool
    {
        if (false !== strpos($key, '.')) {
            $value = $this;
            $parts = explode('.', $key);
            end($parts);
            $lastKey = key($parts);
            foreach ($parts as $key => $part) {
                if (!$value instanceof Map) {
                    return false;
                }
                $value = ($lastKey === $key) ? $value->has($part) : $value->get($part, null, false);
            }

            return $value;
        }
        if (array_key_exists($key, $this->elements)
            && (!$this->elements[$key] instanceof Map || $this->elements[$key]->count() > 0)) {
            return true;
        }

        return false;
    }

    /**
     * Read will return either the value stored with the specified key, or the default value.  This is
     *  essentially same as doing:
     *
     * ```php
     * $value = ($map->has('key')?$map->key:$default);
     * ```
     *
     * It has the added benefits however, of being more streamlined and also allowing the value to be
     * added automatically if it doesn't exist.
     */
    public function &read(string $key, mixed $default, bool $insert = false): mixed
    {
        if (self::has($key)) {
            return self::get($key);
        }
        if ($insert) {
            self::set($key, $default);
        }

        return $default;
    }

    /**
     * Get the default value for a value stored in the Map object.
     *
     * This is useful for getting the original value of a value that has changed.  Such as an original index number or
     * other identifier.
     */
    public function getDefault(string $key): mixed
    {
        if (array_key_exists($key, $this->defaults)) {
            return $this->defaults[$key];
        }

        return false;
    }

    /**
     * Test if there are any changes to this Map object.  Changes include not just changes to element
     * values but any new elements added or any elements being removed.
     *
     * @return bool true if there are any changes/additions/removal of elements, false otherwise
     */
    public function hasChanges(): bool
    {
        $diff1 = array_diff_assoc($this->elements, $this->defaults);
        $diff2 = array_diff(array_keys($this->defaults), array_keys($this->elements));

        return count($diff1 + $diff2) > 0;
    }

    /**
     * Return an array of element value changes that have been made to this Map.
     *
     * @return Map An Map of changed elements
     */
    public function getChanges(): Map
    {
        $changes = array_diff_assoc($this->elements, $this->defaults);
        foreach ($changes as $key => $value) {
            if ($value instanceof Map) {
                $changes[$key] = $value->toArray();
            }
        }

        return new Map($changes);
    }

    /**
     * Test if any values have been removed.
     *
     * @return bool True if one or more values have been removed.  False otherwise.
     */
    public function hasRemoves(): bool
    {
        return count(array_diff(array_keys($this->defaults), array_keys($this->elements))) > 0;
    }

    /**
     * Return a list of keys that have been removed.
     *
     * @return Map a Map of key names that have been removed from this Map
     */
    public function getRemoves(): ?Map
    {
        $removes = [];
        if ($removes = array_flip(array_diff(array_keys($this->defaults), array_keys($this->elements)))) {
            $walk_func = function (&$value) {
                $value = true;
            };
            array_walk($removes, $walk_func);

            return new Map($removes);
        }

        return null;
    }

    /**
     * Test if there are any new elements in the Map.
     *
     * @return bool true if there are new elements, false otherwise
     */
    public function hasNew(): bool
    {
        return count(array_diff(array_keys($this->elements), array_keys($this->defaults))) > 0;
    }

    /**
     * Return any new elements in the Map.
     *
     * @return Map An map of only new elements in the Map
     */
    public function getNew()
    {
        $new = array_diff(array_keys($this->elements), array_keys($this->defaults));
        $array = [];
        foreach ($new as $key) {
            $array[$key] = $this->elements[$key];
        }

        return new Map($array);
    }

    /**
     * Extend the Map using elements from another Map or Array.
     */
    public function extend(): Map
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        foreach (func_get_args() as $elements) {
            if (!$elements) {
                continue;
            }
            if (is_string($elements)) {
                if ($json = json_decode($elements, true)) {
                    $elements = $json;
                }
            }
            if ($elements instanceof \stdClass) {
                $elements = get_object_vars($elements);
            }
            foreach ($elements as $key => $elem) {
                // Here we want to specifically look for a REAL array so we can convert it to a Map
                if (is_array($elem) || $elem instanceof \stdClass) {
                    $elem = new Map($elem, null, $this->filter);
                } elseif ($elem instanceof Map) {
                    $elem->applyFilters($this->filter);
                }
                $elem = $this->execFilter($key, $elem, 'in');
                if ($elem instanceof Map
                    && array_key_exists($key, $this->elements)
                    && $this->elements[$key] instanceof Map) {
                    $this->elements[$key]->extend($elem);
                } else {
                    $this->elements[$key] = $elem;
                }
            }
        }

        return $this;
    }

    /**
     * Pop an element off of the Map.
     *
     * This will by default pop an element off the end of an array.  However this method allows for
     * an element key to be specified which will pop that specific element off the Map.
     *
     * @param string $key Optionally specify the array element to pop off
     *
     * @return mixed The element in the last position of the Map
     */
    public function pop(?string $key = null)
    {
        if (null === $key) {
            if ($this->locked) {
                throw new Exception\LockedMap();
            }

            return array_pop($this->elements);
        }
        if (!array_key_exists($key, $this->elements)) {
            return false;
        }
        $value = $this->elements[$key];
        unset($this->elements[$key]);

        return $value;
    }

    /**
     * Push an element on to the end of the Map.
     */
    public function push(mixed $value): int
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        // Here we want to specifically look for a REAL array so we can convert it to a Map
        if (is_array($value) || $value instanceof \stdClass) {
            $value = new Map($value, null, $this->filter);
        } elseif ($value instanceof Map) {
            $value->applyFilters($this->filter);
        }

        return array_push($this->elements, $value);
    }

    /**
     * Shift an element off of the front of the Map.
     *
     * @return mixed The element in the first position of the Map
     */
    public function shift(): mixed
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }

        return array_shift($this->elements);
    }

    /**
     * Push an element on to the front of the Map.
     */
    public function unshift(mixed $value): int
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        // Here we want to specifically look for a REAL array so we can convert it to a Map
        if (is_array($value) || $value instanceof \stdClass) {
            $value = new Map($value, null, $this->filter);
        } elseif ($value instanceof Map) {
            $value->applyFilters($this->filter);
        }

        return array_unshift($this->elements, $value);
    }

    /**
     * Set an output filter callback to modify objects as they are being returned.
     *
     * @param callable             $callback      the function to execute on get
     * @param array<string>|string $filterType    a class name or array of class names to run the callback on
     * @param bool                 $filterRecurse All children will have the same filter applied
     */
    public function addOutputFilter(
        callable $callback,
        bool $filterRecurse = false,
        ?string $filterField = null,
        null|array|string $filterType = null
    ): void {
        $filter = [
            'callback' => $callback,
            'field' => $filterField,
            'type' => $filterType,
            'recurse' => $filterRecurse,
        ];
        if ($filterRecurse) {
            foreach ($this->elements as $key => $elem) {
                if ($elem instanceof Map) {
                    $elem->addOutputFilter($callback, $filterRecurse, $filterField, $filterType);
                }
            }
        }
        $this->filter['out'][] = $filter;
    }

    /**
     * Set an input filter callback to modify objects as they are being set.
     *
     * @param callable             $callback      the function to execute on set
     * @param array<string>|string $filterType    a class name or array of class names to run the callback on
     * @param bool                 $filterRecurse All children will have the same filter applied
     */
    public function addInputFilter(
        callable $callback,
        bool $filterRecurse = false,
        ?string $filterField = null,
        null|array|string $filterType = null
    ): void {
        $filter = [
            'callback' => $callback,
            'field' => $filterField,
            'type' => $filterType,
            'recurse' => $filterRecurse,
        ];
        if ($filterRecurse) {
            foreach ($this->elements as $key => $elem) {
                if ($elem instanceof Map) {
                    $elem->addInputFilter($callback, $filterRecurse, $filterField, $filterType);
                }
            }
        }
        $this->filter['in'][] = $filter;
    }

    /**
     * Apply a filter array to be used for input/output filters.
     *
     * @param array<string, mixed> $filters_def the filter definition
     *
     * @return bool true if the filter was valid, false otherwise
     */
    public function applyFilters(array $filters_def, bool $recurse = true): bool
    {
        $this->filter = $filters_def;
        if ($recurse) {
            foreach ($this->elements as $key => $value) {
                if ($value instanceof Map) {
                    $value->applyFilters($filters_def);
                }
            }
        }

        return true;
    }

    /**
     * Get a reference to a Map value by key.  If an output filters are set they will be executed
     * before the element is returned here.
     * Filters are applied/executed only for element types specified in the 'out' filter definition.
     *
     * !!! warning
     * Note that when using an output filter the value will NOT be returned by reference meaning
     * in-place modifications will not work.
     *
     * @return mixed Value at key $key
     */
    public function &get(?string $key, mixed $default = null, bool $create = false): mixed
    {
        if (false !== strpos($key, '.')) {
            $value = $this;
            $parts = explode('.', $key);
            end($parts);
            $lastKey = key($parts);
            foreach ($parts as $key => $part) {
                if (!$value instanceof Map) {
                    return Map::_($default);
                }
                $value = $value->get($part, ($key === $lastKey) ? $default : null, $create);
                if (!$value) {
                    if ($default) {
                        $value = Map::_($default);
                    }

                    break;
                }
            }

            return $value;
        }
        if (null === $key && !$this->locked) {
            $elem = new Map(null, null, $this->filter);
            array_push($this->elements, $elem);
        } elseif (!array_key_exists($key, $this->elements) && !$this->locked) {
            if (true === $create && (is_array($default) || $default instanceof \stdClass)) {
                $elem = new Map($default, null, $this->filter);
            } elseif (null !== $default) {
                $elem = $default;
            } elseif (array_key_exists($key, $this->defaults)) {
                $elem = $this->defaults[$key];
            } else {
                $null = null;

                return $null;
            }
            $this->elements[$key] = $elem;
        } else {
            $elem = &$this->elements[$key];
        }
        $elem = $this->execFilter($key, $elem, 'out');

        return $elem;
    }

    /**
     * Magic method to allow -> access to key values.  Calls Map::get().
     *
     * @return mixed Value at key $key
     */
    public function &__get(string $key): mixed
    {
        return $this->get($key, null, true);
    }

    /**
     * Set key value.  Filters are applied/executed at this point for element types specified in the 'in'
     * filter definition.
     */
    public function set(?string $key, mixed $value, bool $merge_arrays = false): bool
    {
        if (false !== strpos($key, '.')) {
            $item = $this;
            $parts = explode('.', $key);
            end($parts);
            $lastKey = key($parts);
            foreach ($parts as $key => $part) {
                if (!$item instanceof Map) {
                    return false;
                }
                if ($key === $lastKey) {
                    return $item->set($part, $value, $merge_arrays);
                }

                $item = $item->get($part, null, true);
            }

            return false;
        }
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        // Here we want to specifically look for a REAL array so we can convert it to a Map
        if (is_array($value) || $value instanceof \stdClass) {
            $value = new Map($value, null, $this->filter);
        }
        $value = $this->execFilter($key, $value, 'in');
        if (null === $key) {
            array_push($this->elements, $value);
        } else {
            if (true === $merge_arrays
                && array_key_exists($key, $this->elements)
                && $this->elements[$key] instanceof Map
                && $value instanceof Map) {
                $this->elements[$key]->extend($value);
            } else {
                $this->elements[$key] = $value;
            }
        }

        return true;
    }

    /**
     * Remove an element from the Map object.
     */
    public function del(string $key): bool
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        unset($this->elements[$key]);

        return true;
    }

    public function offsetExists(mixed $key): bool
    {
        return $this->has($key);
    }

    public function &offsetGet(mixed $key): mixed
    {
        return $this->get($key);
    }

    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function offsetUnset(mixed $key): void
    {
        unset($this->elements[$key]);
    }

    /**
     * Iterates over each element in the map and returns the current key-value pair.
     *
     * @return mixed returns an associative array with the current key and value, or false if there are no more elements
     */
    public function each(): mixed
    {
        if (($key = key($this->elements)) === null) {
            return false;
        }
        $item = ['key' => $key, 'value' => current($this->elements)];
        next($this->elements);

        return $item;
    }

    /**
     * Return the current element in the Map.
     */
    public function current(): mixed
    {
        $key = $this->current['key'];
        $elem = $this->current['value'];

        return $this->execFilter($key, $elem, 'out');
    }

    /**
     * Return the current key from the Map.
     */
    public function key(): mixed
    {
        return $this->current['key'];
    }

    /**
     * Move to the next element in the Map.
     */
    public function next(): void
    {
        $this->current = $this->each();
    }

    /**
     * Set the internal pointer the first element.
     */
    public function rewind(): void
    {
        reset($this->elements);
        $this->current = $this->each();
    }

    /**
     * Test that an element exists at the current internal pointer position.
     */
    public function valid(): bool
    {
        return (bool) $this->current;
    }

    /**
     * Test if a child value is true NULL.  This is the correct way to test for null on a Map object as
     * it will correctly return true for elements that don't exist.
     */
    public function isNull(string $key): bool
    {
        if (!$this->has($key)) {
            return true;
        }

        return null == $this->get($key);
    }

    /**
     * Convert the map to a string.  This is for compatibility with certain other functions that
     * may attempt to use these objects as a string.  If the map contains any elements it will
     * return '%Map', otherwise it will return an empty string.
     */
    public function toString(): string
    {
        return ($this->count() > 0) ? 'Map' : '';
    }

    /**
     * Return the Map as a standard Array.
     *
     * @return array<mixed> The Map object as an array
     */
    public function toArray(bool $ignorenulls = false, bool $filter = true): array
    {
        $array = $this->elements;
        foreach ($array as $key => &$elem) {
            if (true === $filter) {
                $elem = $this->execFilter($key, $elem, 'out');
            }
            if ($elem instanceof Map || $elem instanceof Model) {
                if ($elem->count() > 0) {
                    $elem = $elem->toArray();
                } else {
                    $elem = [];
                }
            }
        }

        return $array;
    }

    /**
     * This is get() and toArray() all in one with the added benefit of checking if $key is a \Hazaar\Map and only calling toArray() if it is.
     */
    public function getArray(string $key, bool $ignorenulls = false): mixed
    {
        $value = $this->get($key);
        if ($value instanceof Map) {
            $value = $value->toArray($ignorenulls);
        }

        return $value;
    }

    /**
     * Return a valid JSON string representation of the Map.
     *
     * @return string The Map as a JSON string
     */
    public function toJSON(bool $ignorenulls = false, int $flags = 0, int $depth = 512): ?string
    {
        if ($array = $this->toArray($ignorenulls)) {
            return json_encode($array, $flags, $depth);
        }

        return null;
    }

    /**
     * Find elements based on search criteria.
     *
     * @param array<string, mixed> $criteria search criteria in the format of key => value
     *
     * @return Map a Map of elements that satisfied the search criteria
     */
    public function find(array $criteria): Map
    {
        $elements = [];
        foreach ($this->elements as $id => $elem) {
            if (!$elem instanceof Map) {
                continue;
            }
            foreach ($criteria as $key => $value) {
                if (!($elem->has($key) && $elem->get($key) === $value)) {
                    continue 2;
                }
            }
            $elements[$id] = $elem;
        }

        return new Map($elements);
    }

    /**
     * Find a sub element based on search criteria.
     *
     * @param array<string, mixed> $criteria search criteria in the format of key => value
     * @param string               $field    Return a single field.  If the field does not exist returns null.  This allows
     *                                       us to safely return a single field in a single line in cases where nothing is found.
     *
     * @return mixed The first element that matches the criteria
     */
    public function &findOne(array $criteria, ?string $field = null): mixed
    {
        foreach ($this->elements as $id => $elem) {
            if (!$elem instanceof Map) {
                continue;
            }
            foreach ($criteria as $key => $value) {
                if (!($elem->has($key) && $elem[$key] === $value)) {
                    continue 2;
                }
            }
            if ($field) {
                return $elem->get($field);
            }

            return $elem;
        }
        $null = null;

        return $null;
    }

    /**
     * Searches a numeric keyed array for a value that is contained within it and returns true if it
     * exists.
     *
     * @param mixed $value The value to search for
     */
    public function contains(mixed $value): bool
    {
        return in_array($value, $this->elements);
    }

    public function search(mixed $value): mixed
    {
        return array_search($value, $this->elements);
    }

    /**
     * Modify multiple elements in one go.  Unlike extends this will only modify a value that already
     * exists in the Map.
     *
     * @param array<string, mixed>|Map $values map of values to update
     */
    public function modify(array|Map $values): void
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        if ($values instanceof Map) {
            $values = $values->toArray();
        }
        foreach ($values as $key => $value) {
            if (self::has($key)) {
                self::set($key, $value);
            }
        }
    }

    /**
     * Enhance is the compliment of modify. It will only update values if they DON'T already exist.
     *
     * @param array<mixed>|Map $values array or Map of values to add
     */
    public function enhance(array|Map $values): void
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        if ($values instanceof Map) {
            $values = $values->toArray();
        }
        foreach ($values as $key => $value) {
            if (!self::has($key)) {
                self::set($key, $value);
            }
        }
    }

    /**
     * Remove an element from the Map based on search criteria.
     *
     * @param array<string, mixed> $criteria an array of ssearch criteria that must be met for the element to be removed
     *
     * @return bool true if something is removed, false otherwise
     */
    public function remove(array $criteria): bool
    {
        if ($this->locked) {
            throw new Exception\LockedMap();
        }
        foreach ($this->elements as $key => $elem) {
            if (!$elem instanceof Map) {
                continue;
            }
            foreach ($criteria as $search_key => $search_value) {
                if (!isset($elem[$search_key])) {
                    continue 2;
                }
                if ((string) $elem[$search_key] != (string) $search_value) {
                    continue 2;
                }
            }
            $this->del($key);

            return true;
        }

        return false;
    }

    /**
     * Return a total of all numeric values in the Map.
     *
     * @param array<string, mixed> $criteria  search criteria that must be met for the value to be included
     * @param array<string>        $fields    The fields to use for the sum.  If omitted all numeric fields will be summed.
     *                                        If a string is specified then a single field will be used.  Also, an Array can
     *                                        be used to allow multiple fields.
     * @param bool                 $recursive set true if you need to recurse into child elements and add them to the sum
     *
     * @return float Sum of all numeric values
     */
    public function sum(null|array|Map $criteria = null, array $fields = [], bool $recursive = false): float
    {
        $children = [];
        $sum = 0;
        foreach ($this->elements as $key => $elem) {
            if ($elem instanceof Map) {
                if ($recursive) {
                    $sum += $elem->sum($criteria, $fields, $recursive);
                } else {
                    continue;
                }
            } else {
                if (null !== $criteria) {
                    foreach ($criteria as $search_key => $search_value) {
                        if (!isset($elem[$search_key])) {
                            continue 2;
                        }
                        if ($elem[$search_key] != $search_value) {
                            continue 2;
                        }
                    }
                }
                if (0 == count($fields) || in_array($key, $fields)) {
                    $sum += (float) $elem;
                }
            }
        }

        return $sum;
    }

    public function filter(\Closure $func, int $mode = 0): void
    {
        $this->elements = array_filter($this->elements, $func, $mode);
    }

    /**
     * Returns an array of key names currently in this Map object.
     *
     * @return array<string> An array of key names
     */
    public function keys(): array
    {
        return array_keys($this->elements);
    }

    /**
     * Returns an array of values currently in this Map object.
     *
     * @return array<mixed> An array of values
     */
    public function values(): array
    {
        return array_values($this->elements);
    }

    /**
     * Lock the map so that it's values can not be accidentally changed.
     */
    public function lock(): void
    {
        $this->locked = true;
    }

    /**
     * Unlock the map so that it's values can be changed.
     */
    public function unlock(): void
    {
        $this->locked = false;
    }

    public function implode(string $glue = ''): string
    {
        return implode($glue, $this->elements);
    }

    /**
     * Flatten the Map into a string.
     *
     * This method will flatten the Map into a string.  The inner glue is used to separate the key and value
     * pairs and the outer glue is used to separate each pair.
     *
     * @param string        $inner_glue the glue to use between the key and value
     * @param string        $outer_glue the glue to use between each key/value pair
     * @param array<string> $ignore     an array of keys to ignore
     *
     * @return string The flattened Map as a string
     */
    public function flatten(string $inner_glue = '=', string $outer_glue = ' ', array $ignore = []): string
    {
        $elements = [];
        foreach ($this->elements as $key => $value) {
            if (in_array($key, $ignore)) {
                continue;
            }
            if ('boolean' == gettype($value)) {
                $value = strbool($value);
            }
            $elements[] = $key.$inner_glue.$value;
        }

        return implode($outer_glue, $elements);
    }

    /**
     * Export all objects/arrays/Maps as an array.
     *
     * If an element is an object it will be checked for an __export() method which if it exists the
     * resulting array from that method will be used as the array representation of the element.  If
     * the method does not exist then the resulting array will be an array of the *public* object member
     * variables only.
     *
     * @param mixed $element        The root element to convert into an array
     * @param bool  $export_as_json instead of returning an array, return a JSON string of the array
     */
    public static function exportAll(mixed $element, bool $export_as_json = false): mixed
    {
        $result = null;
        if ($element instanceof Map) {
            $result = $element->toArray();
        } elseif (is_object($element)) {
            if (method_exists($element, '__export')) {
                $result = Map::exportAll($element->__export());
            } else {
                $members = get_object_vars($element);
                if (count($members) > 0) {
                    $result = [];
                    foreach ($members as $key => $value) {
                        $result[$key] = Map::exportAll($value);
                    }
                }
            }
        } elseif (is_array($element)) {
            $result = $element;
            foreach ($element as $key => $child) {
                $result[$key] = Map::exportAll($child);
            }
        } else {
            $result = $element;
        }
        if ($export_as_json) {
            return json_encode($result);
        }

        return $result;
    }

    /**
     * Convert to dot notation.
     *
     * Converts/reduces a multidimensional array into a single dimensional array with keys in dot-notation.
     *
     * @return Map The Map object as a dot notation Map
     */
    public function toDotNotation(): Map
    {
        return new Map(\array_to_dot_notation($this->toArray()));
    }

    /**
     * Populate or extend the object values from a JSON string.
     */
    public function fromJSON(string $json, bool $merge = false): Map
    {
        $bom = pack('H*', 'EFBBBF');
        $json = preg_replace("/^{$bom}/", '', $json);
        if (($new = json_decode($json, true)) !== null) {
            $merge ? $this->extend($new) : $this->populate($new);
        }

        return $this;
    }

    /**
     * Convert to Map from dot notation.
     *
     * Converts/reduces a single dimensional array with keys in dot-notation and expands it into a
     * multi-dimensional array.
     *
     * @param array<mixed>|Map $array
     */
    public function fromDotNotation(array|Map $array, bool $merge = false): Map
    {
        $new = [];
        foreach ($array as $idx => $value) {
            if (is_array($value)) {
                $new[$idx] = new Map();
                $new[$idx]->fromDotNotation($value, $merge);
            } else {
                $parts = explode('.', $idx);
                if (count($parts) > 1) {
                    $cur = &$new;
                    foreach ($parts as $part) {
                        if (!array_key_exists($part, $cur)) {
                            $cur[$part] = [];
                        }
                        if (is_array($cur)) {
                            $cur = &$cur[$part];
                        }
                    }
                    $cur = $value;
                } else {
                    $new[$idx] = $value;
                }
            }
        }
        $merge ? $this->extend($new) : $this->populate($new);

        return $this;
    }

    /**
     * Updates the Map with values in the supplied array if they exist.
     *
     * This method will update existing values in the current Map object with the values in the supplied $value array
     * or Map.  If the values do not already exist in the current Map object, no new values will be created.
     *
     * @param array<mixed>|Map $values The values to update
     */
    public function update(array|Map $values): Map
    {
        foreach ($values as $key => $value) {
            if (!$this->has($key)) {
                continue;
            }
            $elem = $this->get($key);
            if ($elem instanceof Map) {
                $elem->update($value);
            } else {
                $this->set($key, $value);
            }
        }

        return $this;
    }

    /**
     * Decodes the value associated with the given key.
     *
     * @param string $key the key to decode the value for
     *
     * @return mixed the decoded value
     */
    public function decode(string $key): mixed
    {
        $value = $this->get($key);
        if ($value instanceof Map) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Execute a filter on the given element.
     *
     * @internal
     *
     * @param int|string $key       The name of the key to pass to the callback
     * @param mixed      $elem      The element that we are executing the callback on
     * @param string     $direction The filter direction identified ('in' or 'out')
     *
     * @return mixed Returns the element once it has been passed through the callback
     */
    private function execFilter(int|string $key, mixed $elem, string $direction): mixed
    {
        if (array_key_exists($direction, $this->filter) && count($this->filter[$direction]) > 0) {
            foreach ($this->filter[$direction] as $field => $filter) {
                if (array_key_exists('field', $filter) && (null !== $filter['field'] && $key != $filter['field'])) {
                    continue;
                }
                if (array_key_exists('type', $filter)) {
                    if (!is_array($filter['type'])) {
                        $filter['type'] = [$filter['type']];
                    }
                    foreach ($filter['type'] as $type) {
                        if (!$type || is_a($elem, $type)) {
                            $elem = call_user_func($filter['callback'], $elem, $key);
                        }
                    }
                } else {
                    $elem = call_user_func($filter['callback'], $elem, $key);
                }
            }
        }

        return $elem;
    }
}
