<?php

namespace Hazaar\Model;

abstract class Strict implements \ArrayAccess, \Iterator {

    /**
     * Undefined values will be ignored. This is checked first.
     * @var bool
     */
    protected $ignore_undefined = true;

    /**
     * Undefined values will be automatically added and their type will be detected.
     * @var bool
     */
    protected $allow_undefined = false;

    /**
     * Disable all callbacks while this is true.
     * @var bool
     */
    protected $disable_callbacks = false;

    /**
     * Automatically convert null values to empty strings if the field is of type string.
     * @var bool
     */
    protected $convert_nulls = false;

    /**
     * The list of known variable types that are supported by strict models.
     * @var mixed
     */
    private static $known_types = array(
        'boolean',
        'integer',
        'int',
        'float',
        'double',  // for historical reasons "double" is returned in case of a float, and not simply "float"
        'string',
        'array',
        'list',
        'object',
        'resource',
        'NULL',
        'model',
        'mixed'
    );

    /**
     * Aliases for any special variable types that we support that will be used for system functions.
     * @var mixed
     */
    private static $type_aliases = array(
        'bool' => 'boolean',
        'number' => 'float',
        'text' => 'string'
    );

    /**
     * The field definition.
     * @var mixed
     */
    protected $fields = array();

    /**
     * The current values of all defined fields.
     * @var mixed
     */
    protected $values = array();

    /**
     * Internal loaded flag.  This allows read only fields to be set during startup.
     * @var mixed
     */
    private $loaded = false;

    /**
     * The current value for array access returned by each().
     * @var mixed
     */
    protected $current;

    /**
     * Strict model constructor
     *
     * The constructor has optional parameters that can vary depending on the implementation.  The first parameter is
     * an array containing the initial data to be loaded into the model.  Any subsequent arguments are passed directly
     * and "as is" to the Hazaar\Model\Strict::construct() method implemented by the extending class.
     *
     * @param $data array Initial data to be loaded into the model.
     *
     * @throws Exception\InitMissing
     *
     * @throws Exception\BadFieldDefinition
     */
    function __construct() {

        $args = func_get_args();

        $data = array_shift($args);

        if (!method_exists($this, 'init'))
            throw new Exception\InitMissing(get_class($this));

        $field_definition = $this->init();

        if (!is_array($field_definition))
            throw new Exception\BadFieldDefinition();

        $this->loadDefinition($field_definition);

        if (is_object($data))
            $data = (array) $data;

        $this->prepare($data);

        if (is_array($data) && count($data) > 0)
            $this->populate($data);

        $this->loaded = true;

        if (method_exists($this, 'construct'))
            call_user_func_array(array($this, 'construct'), $args);

    }

    /**
     * Strict model destructor
     *
     * The destructor calls the shutdown method to allow the extending class to do any cleanup functions.
     */
    function __destruct() {

        if (method_exists($this, 'shutdown'))
            $this->shutdown();

    }

    public function prepare(&$data){

    }

    /**
     * Populate the model with data contained in the supplied array.
     *
     * @param mixed $data The array of data.
     *
     * @param mixed $exec_filters Execute any callback filters.  For populate this is disabled by default.
     *
     * @return boolean
     */
    public function populate($data, $exec_filters = false) {

        if (!\Hazaar\Map::is_array($data))
            return false;

        foreach($data as $key => $value)
            $this->set($key, $value, $exec_filters);

        return true;

    }

    /**
     * Loads the provided field definition into the model.
     *
     * @param array $field_definition
     */
    public function loadDefinition(array $field_definition) {

        $this->fields = $field_definition;

        $this->values = array();

        if (is_array($this->fields) && count($this->fields) > 0) {

            foreach($this->fields as $field => & $definition) {

                if ($field == '*')
                    continue;

                if (is_array($definition)) {

                    /*
                     * If a type is defined and it is an array, list, or model, then prepare the value as an empty array.
                     */
                    if (array_key_exists('type', $definition)
                        && ($definition['type'] == 'array' || $definition['type'] == 'list' || $definition['type'] == 'model')
                        && !array_key_exists('default', $definition)) {

                        if (array_key_exists('items', $definition)) {

                            $value = new SubModel($definition['items']);

                            $definition['type'] = 'model';

                        } else {

                            $value = array();

                        }

                        /*
                         * If a default value is defined then set the current field value to that.
                         */
                    } elseif (array_key_exists('default', $definition)) {

                        $value = $definition['default'];

                        if ($value !== null && array_key_exists('type', $definition)) {

                            if ($definition['type'] == 'model') {

                                $def = array();

                                if ($arrayOf = ake($definition, 'arrayOf'))
                                    $def = array('*' => array('type' => $arrayOf));

                                $value = new SubModel($def, $value);

                            } elseif (in_array($definition['type'], Strict::$known_types)) {

                                if (\Hazaar\Map::is_array($value) && count($value) == 0)
                                    $value = null;

                                $value = $this->convertType($value, $definition['type']);

                            } elseif (class_exists($definition['type']) && !is_a($value, $definition['type'])) {

                                $value = new $definition['type']($value);

                            }

                        }

                    } else { //Otherwise set it to null

                        $value = null;

                    }

                    $this->values[$field] = $value;

                } else {

                    if ($definition == 'array' || $definition == 'list')
                        $value = array();
                    else
                        $value = null;

                    $this->values[$field] = $value;

                }

            }

        }

    }

    /**
     * Return the field definition for the requested field.
     *
     * @param mixed $key The key of the field to return the definition for.
     *
     * @return array
     */
    public function getDefinition($key){

        $def = ake($this->fields, $key);

        if(is_string($def))
            $def = array('type' => $def);

        return $def;

    }

    /**
     * Return true/false indicating if a field has been defined.
     *
     * If the field is not "defined" but instead $allow_undefined has been enabled and the field was added, this will also return true.
     *
     * @param mixed $key The field name to check.
     *
     * @return boolean
     */
    public function has($key) {

        return array_key_exists($key, $this->fields);

    }

    /**
     * Test if any fields have non-null values
     *
     * @return boolean
     */
    public function hasValues(){

        foreach($this->fields as $key => $def){

            $type = ake($def, 'type');

            if($type == 'array'){

                if(count(ake($this->values, $key, array())) > 0)
                    return true;

            }elseif(ake($this->values, $key) !== null)
                return true;

        }

        return false;

    }

    public function ake($key, $default = null, $non_empty = false) {

        $value = $this->get($key);

        if ($value !== null && (!$non_empty || ($non_empty && trim($value))))
            return $value;

        return $default;

    }

    public function &__get($key) {

        return $this->get($key, !$this->disable_callbacks);

    }

    public function &get($key, $exec_filters = true) {

        if (!array_key_exists($key, $this->values)) {

            $null = null;

            return $null;

        }

        $value = &$this->values[$key];

        $def = ake($this->fields, $key, ake($this->fields, '*'));

        /*
         * Run any pre-read callbacks
         */
        if ($exec_filters && is_array($def) && array_key_exists('read', $def)){

            $value = $this->execCallback($def['read'], $value, $key);

            if($type = ake($def, 'type'))
                $this->convertType($value, $type);

        }

        return $value;

    }

    public function __set($key, $value) {

        return $this->set($key, $value, !$this->disable_callbacks);

    }

    public function isObject($key){

        $type = $this->getType($key);

        if($type == 'object' || !(in_array($type, Strict::$known_types) || array_key_exists($type, Strict::$type_aliases)))
            return true;

        return false;

    }

    public function getType($key){

        if($field = ake($this->fields, $key)){

            if(!is_array($field))
                return $field;

            return ake($field, 'type', gettype($this->values[$key]));

        }

        return gettype(ake($this->values, $key, false));

    }

    protected function convertType(&$value, $type) {

        if(array_key_exists($type, Strict::$type_aliases))
            $type = Strict::$type_aliases[$type];

        if($value instanceof \Iterator
            || $value instanceof \ArrayIterator
            || $value instanceof \IteratorAggregate)
            $value = iterator_to_array($value);

        if (in_array($type, Strict::$known_types)) {

            if(is_array($value) && array_key_exists('__hz_value', $value) && array_key_exists('__hz_label', $value)){

                if($type !== 'array'){

                    $value = new dataBinderValue(ake($value, '__hz_value'), ake($value, '__hz_label'));

                    $this->convertType($value->value, $type);

                    return $value;

                }

                $value = null;

            }

            /*
             * The special type 'mixed' specifically allow
             */
            if ($type == 'mixed' || $type == 'model')
                return $value;

            if (\Hazaar\Map::is_array($value) && count($value) == 0)
                $value = null;

            if ($type == 'boolean') {

                $value = boolify($value);

            }elseif($type == 'list'){

                if(!is_array($value))
                    @settype($value, 'array');
                else
                    $value = array_values($value);

            } elseif ($type == 'string' && is_object($value) && method_exists($value, '__tostring')) {

                if ($value !== null)
                    $value = (string) $value;

            } elseif ($type !== 'string' && ($value === '' || $value === 'null')){

                $value = null;

            } elseif (!@settype($value, $type)) {

                throw new Exception\InvalidDataType($type, get_class($value));

            }

        } elseif (class_exists($type)) {

            if (!is_a($value, $type)) {

                try {

                    $value = new $type($value);

                }
                catch(\Exception $e) {

                    $value = null;

                }

            }

        }

        return $value;

    }

    public function set($key, $value, $exec_filters = true) {

        /*
         * Keep the field definition handy
         *
         * If it is an array, it is a complex type
         * If it is a string, it is a simple type, so convert to an array automatically with just a type def
         */
        $def = ake($this->fields, $key, ake($this->fields, '*'));

        if (!$def) {

            if ($this->ignore_undefined === true)
                return null;

            elseif ($this->allow_undefined === false)
                throw new Exception\FieldUndefined($key);

            $this->fields[$key] = gettype($value);

        }

        if (!is_array($def))
            $def = array('type' => $def);

        /*
         * Static/Ready-Only check.
         *
         * If a value is static then updates to it are not allowed and are silently ignored
         */
        if ((array_key_exists('static', $def) && $def['static'] === true)
            || (array_key_exists('readonly', $def) && $def['readonly'] && $this->loaded))
            return false;

        /*
         * Run any pre-update callbacks
         */
        if ($exec_filters && array_key_exists('update', $def) && array_key_exists('pre', $def['update']))
            $value = $this->execCallback($def['update']['pre'], $value, $key);

        /*
         * Type check
         *
         * This checks the type of the field against the type of the value being set and converts it if needed.
         *
         * NOTE: Nulls are not converted as they may have special meaning.
         */
        if ($value !== null && array_key_exists('type', $def))
            $this->convertType($value, $def['type']);

        /*
         * null value check.
         */

        if ($value === null && array_key_exists('nulls', $def) && $def['nulls'] == false) {

            if (array_key_exists('default', $def))
                $value = $def['default'];
            else
                return false;

        }

        if (\Hazaar\Map::is_array($value) && array_key_exists('arrayOf', $def)) {

            if (is_array($value)) {

                foreach($value as & $subValue)
                    $this->convertType($subValue, $def['arrayOf']);

            } else {

                foreach($value as $subKey => $subValue)
                    $value[$subKey] = $this->convertType($subValue, $def['arrayOf']);

            }

        }elseif(array_key_exists('type', $def) && $def['type'] == 'array' && is_array($value)){

            foreach($value as & $subValue){

                if(is_array($subValue) && array_key_exists('__hz_value', $subValue) && array_key_exists('__hz_label', $subValue))
                    $subValue = new dataBinderValue(ake($subValue, '__hz_value'), ake($subValue, '__hz_label'));

            }

        }

        /*
         * Field validation
         *
         * Possible validation handlers are:
         * * min - minimum integer value
         * * max - maximum integer value
         * * with - string regular expression matching
         */

        if ($exec_filters === true && array_key_exists('validate', $def)) {

            foreach($def['validate'] as $type => $data) {

                switch ($type) {
                    case 'min' :
                        if ($value < $data)
                            return false;

                        break;

                    case 'max' :
                        if ($value > $data)
                            return false;

                        break;

                    case 'with' :
                        if (!preg_match('/' . $data . '/', strval($value)))
                            return false;

                        break;

                    case 'equals' :
                        if ($value !== $data)
                            return false;

                        break;

                    case 'minlen' :
                        if($def['type'] == 'array'){

                            if(count($value) < $data)
                                return false;

                        }else{

                            if (strlen($value) < $data)
                                return false;
                        }

                        break;

                    case 'maxlen' :

                        if($def['type'] == 'array'){

                            if(count($value) > $data)
                                return false;

                        }else{

                            if (strlen($value) > $data)
                                return false;

                        }

                        break;

                }

            }

        }

        /*
         * Apply any sort methods
         */
        if(ake($def, 'type') == 'array' && $sort = ake($def, 'sort')){

            if(is_callable($sort)){

                usort($value, $sort);

            }else{

                switch($sort){

                    case 'asort':
                        asort($value);
                        break;

                    case 'ksort':
                        ksort($value);
                        break;

                    default:
                        sort($value);
                        break;

                }

            }

        }

        $old_value = null;

        if (ake($def, 'type') == 'model') {

            if ($this->values[$key] instanceof SubModel)
                $this->values[$key]->populate($value);

        } else {

            /*
             * Store the old value to pass to the callback;
             */
            $old_value = ake($this->values, $key);

            /*
             * Now that we have passed our checks, store the new value
             */
            $this->values[$key] = $value;

        }

        /*
         * Run any post-update callbacks
         */
        if ($exec_filters && array_key_exists('update', $def) && array_key_exists('post', $def['update']))
            $this->execCallback($def['update']['post'], $old_value, $key);

        return $value;

    }

    /**
     * Append an element to an array item
     *
     * @param mixed $key
     * @param mixed $item
     */
    public function append($key, $item){

        if(!($def = ake($this->fields, $key)))
            return null;

        $type = ake($def, 'type');

        if(strtolower($type) != 'array' && strtolower($type) != 'list')
            return null;

        if($arrayOf = ake($def, 'arrayOf'))
            $this->convertType($item, $arrayOf);

        if(! (array_key_exists($key, $this->values) && is_array($this->values[$key])))
            $this->values[$key] = array();

        array_push($this->values[$key], $item);

        return $item;

    }

    /**
     * @detail Execute the a callback function on a key/value pair.
     *
     * @param Mixed $cb_def     The callback definition
     *
     * @param mixed $value      The current value of the element.
     *
     * @param string $key       The key name of the element.
     *
     * @return mixed
     */
    private function execCallback($cb_def, $value, $key) {

        if (is_array($cb_def)) {

            $callback = array_slice($cb_def, 0, 2);

            $params = array(
                $value,
                $key
            );

            if (array_key_exists(2, $cb_def) && is_array($cb_def[2]))
                $params = array_merge($params, $cb_def[2]);

            $value = call_user_func_array($callback, $params);

        } elseif ($cb_def instanceof \Closure) {

            $value = $cb_def($value, $key);

        } elseif (is_string($cb_def)) {

            $value = $cb_def($value, $key);

        }

        return $value;

    }

    public function extend($values, $exec_filters = true, $ignore_keys = null) {

        if (\Hazaar\Map::is_array($values)) {

            foreach($values as $key => $value) {

                if(is_array($ignore_keys) && in_array($key, $ignore_keys))
                    continue;

                if (!array_key_exists($key, $this->values))
                    continue;

                if ($this->values[$key] instanceof Strict) {

                    $this->values[$key]->extend($value, $exec_filters, $ignore_keys);

                } else {

                    if(is_array($value)){

                        $def = ake($this->fields, $key);

                        if($type = ake($def, 'arrayOf')){

                            if($extend = ake($def, 'extend')){

                                if(is_callable($extend))
                                    $this->set($key, $extend($value, $this->values[$key]));

                            }else{

                                foreach($value as $subKey => $subValue){

                                    if(ake($this->values[$key], $subKey) instanceof Strict)
                                        $this->values[$key][$subKey]->extend($subValue, $exec_filters, $ignore_keys);

                                    else
                                        $this->values[$key][$subKey] = $this->convertType($subValue, $type);

                                }

                            }

                        }else{

                            if(is_array($this->values[$key]))
                                $value = array_merge($this->values[$key], $value);

                            $this->set($key, $value, $exec_filters);

                        }

                    }else{

                        $this->set($key, $value, $exec_filters);

                    }

                }

            }

        }

        return true;

    }

    /**
     * Convert data into an array
     *
     * If field values are Strict models, then convert them to arrays as well.
     *
     * @since 1.0.0
     */
    public function toArray($disable_callbacks = false, $depth = null, $show_hidden = true) {

        return $this->resolveArray($this, $disable_callbacks, $depth, $show_hidden);

    }

    private function resolveArray($array, $disable_callbacks = false, $depth = null, $show_hidden = false) {

        $result = array();

        $callback_state = $this->disable_callbacks;

        $this->disable_callbacks = $disable_callbacks;

        foreach($array as $key => $value) {

            $def = ake($this->fields, $key, ake($this->fields, '*'));

            if (!is_array($def))
                $def = array('type' => $def);

            /*
             * Hiding fields
             *
             * If the definition for this field has the 'hide' attribute, we check if the value matches and if so we skip
             * this value.
             */

            if ($show_hidden === false && array_key_exists($key, $this->fields) && is_array($this->fields[$key]) && array_key_exists('hide', $this->fields[$key])) {

                $hide = $this->fields[$key]['hide'];

                if ($hide instanceof \Closure)
                    $hide = $hide($value);

                if ($hide === true)
                    continue;
            }

            /*
             * Run any toArray callbacks
             */
            if (!$disable_callbacks && array_key_exists('toArray', $def))
                $value = $this->execCallback($def['toArray'], $value, $key);

            if ($depth === null || $depth > 0) {

                $next = $depth ? $depth - 1 : null;

                if ($value instanceof Strict) {

                    $value = $value->toArray($disable_callbacks, $next, $show_hidden);

                } elseif ($value instanceof dataBinderValue) {

                    $value = $value->toArray();

                } elseif (is_array($value)) {

                    $value = $this->resolveArray($value, $disable_callbacks, $next, $show_hidden);

                }

            }

            if($value === null && ake(ake($this->fields, $key), 'type', 'string') && $this->convert_nulls)
                $value = '';

            $result[$key] = $value;
        }

        $this->disable_callbacks = $callback_state;

        return $result;

    }

    /**
     * Array Access Methods
     */
    public function offsetExists($offset) {

        return array_key_exists($offset, $this->values);

    }

    public function &offsetGet($offset) {

        return $this->get($offset);

    }

    public function offsetSet($offset, $value) {

        return $this->set($offset, $value);

    }

    public function offsetUnset($offset) {

        unset($this->values[$offset]);

    }

    /**
     * @detail Return the current element in the Map
     *
     * @since 1.0.0
     */
    public function current() {

        $key = $this->current['key'];

        $value = $this->current['value'];

        $def = ake($this->fields, $key, ake($this->fields, '*', array()));

        /*
         * Run any pre-read callbacks
         */
        if (!$this->disable_callbacks && is_array($def) && array_key_exists('read', $def))
            return $def['read']($value, $key);

        return $value;

    }

    /**
     * @detail Return the current key from the Map
     *
     * @since 1.0.0
     */
    public function key() {

        return $this->current['key'];

    }

    /**
     * @detail Move to the next element in the Map
     *
     * @since 1.0.0
     */
    public function next() {

        if ($this->current = each($this->values))
            return true;

        return false;

    }

    /**
     * @detail Set the internal pointer the first element
     *
     * @since 1.0.0
     */
    public function rewind() {

        reset($this->values);

        $this->current = each($this->values);

    }

    /**
     * @detail Test that an element exists at the current internal pointer position
     *
     * @since 1.0.0
     */
    public function valid() {

        if ($this->current)
            return true;

        return false;

    }

    /**
     * Returns the number of fields stored in the model.
     *
     * @return int
     *
     * @since 1.3.0
     */
    public function count() {

        return count($this->values);

    }

    /**
     * Export the mdel in HazaarModelView format for easy display in views.
     *
     * @return array The array of values stored in the model in key => (label, value) tuples.  Returns null if model is empty.
     *
     * @since 2.0.0
     */
    public function exportHMV($ignore_empty = false, $export_all = false, $obj = null){

        if(!$obj)
            $obj = new \Hazaar\Map($this->toArray(false, 0, $export_all));

        return $this->exportHMVArray($this->toArray(false, 0, $export_all), $this->fields, $ignore_empty, $export_all, $obj);

    }

    /**
     * Exports and array in HazaarModelView format using the supplied definition
     *
     * @param mixed $array The array to convert and export.
     *
     * @param mixed $def   The strict model definition.
     *
     * @return array       The array of values in key => (label, value) tuples.  Returns null if first parameter is not an array.
     *
     * @since 2.0.0
     */
    private function exportHMVArray($array, $def, $hide_empty = false, $export_all = false, $object = null){

        if(!is_array($array))
            return null;

        $values = array();

        foreach($array as $key => $value){

            if(!($key_def = ake($def, $key)) && !$export_all)
                continue;

            //If there is no key definition (because we are export_all=true) then use an empty array so things don't break
            if(!is_array($key_def))
                $key_def = array();

            if(ake($key_def, 'force_hide') === true)
                continue;

            if($when = ake($key_def, 'when')){

                if(is_callable($when)){

                    if(!call_user_func($when))
                        continue;

                }else{

                    if(!$object->findOne($when))
                        continue;

                }

            }

            $values[$key] = $key_def;

            if($value instanceof Strict){

                if($value->count() == 0 && ($hide_empty || ake($key_def, 'force_hide_empty') == true))
                    continue;

                if(method_exists($value, '__toString')){

                    $values[$key]['value'] = $value->__toString();

                }else{

                    $values[$key]['items'] = $value->exportHMV($hide_empty, $export_all, $object);

                }

            }elseif(is_array($value)){

                if(count($value) == 0 && ($hide_empty || ake($key_def, 'force_hide_empty') == true))
                    continue;

                foreach($value as $subKey => $subValue){

                    if(empty($subValue) && $hide_empty)
                        continue;

                    if ($subValue instanceof Strict){

                        if(method_exists($subValue, 'export')){

                            $values[$key]['items'][] = $subValue->export();

                        }elseif(method_exists($subValue, '__toString')){

                            $values[$key]['list'][] = (string)$subValue;

                        }else{

                            $values[$key]['collection'][] = $subValue->exportHMV($hide_empty, $export_all, $object);

                        }

                    }elseif(is_array($subValue)){

                        $subDef = $key_def;

                        $values[$key]['collection'][] = $this->exportHMVArray($subValue, (is_array($subDef)?$subDef:array()), $hide_empty, $export_all, $object);

                    }else{

                        if(!array_key_exists('items', $values[$key]))
                            $values[$key]['items'] = array();

                        $values[$key]['items'][] = array('label' => $subKey, 'value' => $subValue);

                    }

                }

            }else{

                if(empty($value) && ($hide_empty || ake($key_def, 'force_hide_empty') == true))
                    continue;

                $values[$key]['value'] = $value;

            }

        }

        return $values;

    }

}

class SubModel extends Strict {

    function __construct($field_definition, $values = array()) {

        parent::loadDefinition($field_definition);

        if ($values)
            $this->populate($values);

    }

}

