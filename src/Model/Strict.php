<?php

namespace Hazaar\Model;

abstract class Strict implements \ArrayAccess, \Iterator {

    /**
     *
     * @param
     *            Undefined values will be ignored. This is checked first.
     */
    protected $ignore_undefined = TRUE;

    /**
     *
     * @param
     *            Undefined values will be automatically added and their type will be detected
     */
    protected $allow_undefined = FALSE;

    private static $known_types = array(
        'boolean',
        'bool',
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

    protected $fields = array();

    protected $values = array();

    protected $disable_callbacks = FALSE;

    protected $convert_nulls = FALSE;

    private $loaded = FALSE;

    /**
     * The current value for array access returned by each()
     */
    protected $current;

    protected $__label;

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

        $this->loaded = TRUE;

        if (method_exists($this, 'construct'))
            call_user_func_array(array($this, 'construct'), $args);

    }

    function __destruct() {

        if (method_exists($this, 'shutdown'))
            $this->shutdown();

    }

    public function prepare(&$data){

    }

    public function populate($data, $exec_filters = FALSE) {

        if (!\Hazaar\Map::is_array($data))
            return FALSE;

        foreach($data as $key => $value)
            $this->set($key, $value, $exec_filters);

        return TRUE;

    }

    public function loadDefinition(array $field_definition) {

        $this->fields = $field_definition;

        $this->values = array();

        if (is_array($this->fields) && count($this->fields) > 0) {

            foreach($this->fields as $field => & $definition) {

                if ($field == '*')
                    continue;

                if (is_array($definition)) {

                    if (array_key_exists('type', $definition) && ($definition['type'] == 'array' || $definition['type'] == 'list' || $definition['type'] == 'model') && !array_key_exists('default', $definition)) {

                        if (array_key_exists('items', $definition)) {

                            $value = new SubModel($definition['items']);

                            $definition['type'] = 'model';

                        } else {

                            $value = array();

                        }

                    } elseif (array_key_exists('default', $definition)) {

                        $value = $definition['default'];

                        if ($value !== NULL && array_key_exists('type', $definition)) {

                            if ($definition['type'] == 'model') {

                                $def = array();

                                if ($arrayOf = ake($definition, 'arrayOf'))
                                    $def = array('*' => array('type' => $arrayOf));

                                $value = new SubModel($def, $value);

                            } elseif (in_array($definition['type'], Strict::$known_types)) {

                                if (\Hazaar\Map::is_array($value) && count($value) == 0)
                                    $value = NULL;

                                if (!settype($value, $definition['type']))
                                    throw new Exception\InvalidDataType($field, $definition['type'], get_class($value));

                            } elseif (class_exists($definition['type']) && !is_a($value, $definition['type'])) {

                                $value = new $definition['type']($value);

                            }

                        }

                    } else {

                        $value = NULL;

                    }

                    $this->values[$field] = $value;

                } else {

                    if ($definition == 'array' || $definition == 'list')
                        $value = array();
                    else
                        $value = NULL;

                    $this->values[$field] = $value;

                }

            }

        }

    }

    public function getDefinition($key){

        $def = ake($this->fields, $key);

        if(is_string($def))
            $def = array('type' => $def);

        return $def;

    }

    public function has($key) {

        return array_key_exists($key, $this->fields);

    }

    public function ake($key, $default = NULL, $non_empty = FALSE) {

        $value = $this->get($key);

        if ($value !== NULL && (!$non_empty || ($non_empty && trim($value))))
            return $value;

        return $default;

    }

    public function &__get($key) {

        return $this->get($key, !$this->disable_callbacks);

    }

    public function &get($key, $run_callbacks = TRUE) {

        if (!array_key_exists($key, $this->values)) {

            $null = NULL;

            return $null;

        }

        $value = &$this->values[$key];

        $def = ake($this->fields, $key, ake($this->fields, '*'));

        /*
         * Run any pre-read callbacks
         */
        if ($run_callbacks && is_array($def) && array_key_exists('read', $def))
            $value = $this->execCallback($def['read'], $value, $key);

        return $value;

    }

    public function __set($key, $value) {

        return $this->set($key, $value, !$this->disable_callbacks);

    }

    public function isObject($key){

        $type = $this->getType($key);

        if($type == 'object' || !in_array($type, Strict::$known_types))
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

    protected function convertType($value, $type) {

        if (in_array($type, Strict::$known_types)) {

            /*
             * The special type 'mixed' specifically allow
             */
            if ($type == 'mixed' || $type == 'model')
                return $value;

            if (\Hazaar\Map::is_array($value) && count($value) == 0)
                $value = NULL;

            $bools = array(
                'bool',
                'boolean'
            );

            if (in_array($type, $bools)) {

                $value = boolify($value);

            }elseif($type == 'list'){

                if(!is_array($value))
                    @settype($value, 'array');
                else
                    $value = array_values($value);

            } elseif ($type == 'string' && is_object($value) && method_exists($value, '__tostring')) {

                if ($value !== NULL)
                    $value = (string) $value;

            } elseif (!@settype($value, $type)) {

                throw new Exception\InvalidDataType($type, get_class($value));

            }

        } elseif (class_exists($type)) {

            if (!is_a($value, $type)) {

                try {

                    $value = new $type($value);

                }
                catch(\Exception $e) {

                    $value = NULL;

                }

            }

        }

        return $value;

    }

    public function set($key, $value, $run_callbacks = TRUE) {

        /*
         * Keep the field definition handy
         *
         * If it is an array, it is a complex type
         * If it is a string, it is a simple type, so convert to an array automatically with just a type def
         */
        $def = ake($this->fields, $key, ake($this->fields, '*'));

        if (!$def) {

            if ($this->ignore_undefined === TRUE)
                return NULL;

            elseif ($this->allow_undefined === FALSE)
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
        if ((array_key_exists('static', $def) && $def['static'] === TRUE) || (array_key_exists('readonly', $def) && $def['readonly'] && $this->loaded))
            return FALSE;

        /*
         * Run any pre-update callbacks
         */
        if ($run_callbacks && array_key_exists('update', $def) && array_key_exists('pre', $def['update']))
            $value = $this->execCallback($def['update']['pre'], $value, $key);

        /*
         * Type check
         *
         * This checks the type of the field against the type of the value being set and converts it if needed.
         *
         * NOTE: Nulls are not converted as they may have special meaning.
         */
        if ($value !== NULL && array_key_exists('type', $def))
            $value = $this->convertType($value, $def['type']);

        /*
         * NULL value check.
         */

        if ($value === NULL && array_key_exists('nulls', $def) && $def['nulls'] == FALSE) {

            if (array_key_exists('default', $def))
                $value = $def['default'];
            else
                return FALSE;

        }

        if (\Hazaar\Map::is_array($value) && array_key_exists('arrayOf', $def)) {

            if (is_array($value)) {

                foreach($value as & $subValue)
                    $subValue = $this->convertType($subValue, $def['arrayOf']);

            } else {

                foreach($value as $subKey => $subValue)
                    $value[$subKey] = $this->convertType($subValue, $def['arrayOf']);

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

        if (array_key_exists('validate', $def)) {

            foreach($def['validate'] as $type => $data) {

                switch ($type) {
                    case 'min' :
                        if ($value < $data)
                            return FALSE;

                        break;

                    case 'max' :
                        if ($value > $data)
                            return FALSE;

                        break;

                    case 'with' :
                        if (!preg_match($data, $value))
                            return FALSE;

                        break;

                    case 'equals' :
                        if ($value !== $data)
                            return FALSE;

                        break;

                    case 'minlen' :
                        if (strlen($value) < $data)
                            return FALSE;

                        break;

                    case 'maxlen' :
                        if (strlen($value) > $data)
                            return FALSE;

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

        $old_value = NULL;

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
        if ($run_callbacks && array_key_exists('update', $def) && array_key_exists('post', $def['update']))
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
            $item = $this->convertType($item, $arrayOf);

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

    public function extend($values, $run_callbacks = TRUE, $ignore_keys = null) {

        if (\Hazaar\Map::is_array($values)) {

            foreach($values as $key => $value) {

                if(is_array($ignore_keys) && in_array($key, $ignore_keys))
                    continue;

                if (!array_key_exists($key, $this->values))
                    continue;

                if ($this->values[$key] instanceof Strict) {

                    $this->values[$key]->extend($value, $run_callbacks, $ignore_keys);

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
                                        $this->values[$key][$subKey]->extend($subValue, $run_callbacks, $ignore_keys);

                                    else
                                        $this->values[$key][$subKey] = $this->convertType($subValue, $type);

                                }

                            }

                        }else{

                            if(is_array($this->values[$key]))
                                $value = array_merge($this->values[$key], $value);

                            $this->set($key, $value, $run_callbacks);

                        }

                    }else{

                        $this->set($key, $value, $run_callbacks);

                    }

                }

            }

        }

        return TRUE;

    }

    /**
     * Convert data into an array
     *
     * If field values are Strict models, then convert them to arrays as well.
     *
     * @since 1.0.0
     */
    public function toArray($disable_callbacks = FALSE, $depth = NULL, $show_hidden = FALSE) {

        return $this->resolveArray($this, $disable_callbacks, $depth, $show_hidden);

    }

    private function resolveArray($array, $disable_callbacks = FALSE, $depth = NULL, $show_hidden = FALSE) {

        $result = array();

        $callback_state = $this->disable_callbacks;

        $this->disable_callbacks = $disable_callbacks;

        foreach($array as $key => $value) {

            /*
             * Hiding fields
             *
             * If the definition for this field has the 'hide' attribute, we check if the value matches and if so we skip
             * this value.
             */

            if ($show_hidden === FALSE && array_key_exists($key, $this->fields) && is_array($this->fields[$key]) && array_key_exists('hide', $this->fields[$key])) {

                $hide = $this->fields[$key]['hide'];

                if ($hide instanceof \Closure)
                    $hide = $hide($value);

                if ($hide === TRUE)
                    continue;
            }

            if ($depth === NULL || $depth > 0) {

                $next = $depth ? $depth - 1 : NULL;

                if ($value instanceof Strict) {

                    $value = $value->toArray($disable_callbacks, $next, $show_hidden);

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
            return TRUE;

        return FALSE;

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
            return TRUE;

        return FALSE;

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

    public function label(){

        return $this->__label;

    }

    /**
     * Export the mdel in HazaarModelView format for easy display in views.
     *
     * @return array The array of values stored in the model in key => (label, value) tuples.  Returns NULL if model is empty.
     *
     * @since 2.0.0
     */
    public function exportHMV($ignore_empty = false, $export_all = false, $obj = null){

        if(!$obj)
            $obj = new \Hazaar\Map($this->toArray());

        return $this->exportHMVArray($this->toArray(false, 0), $this->fields, $ignore_empty, $export_all, $obj);

    }

    /**
     * Exports and array in HazaarModelView format using the supplied definition
     *
     * @param mixed $array The array to convert and export.
     *
     * @param mixed $def   The strict model definition.
     *
     * @return array       The array of values in key => (label, value) tuples.  Returns NULL if first parameter is not an array.
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

                            $values[$key]['collection'][] = $subValue->exportHMV($hide_empty, $object);

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

