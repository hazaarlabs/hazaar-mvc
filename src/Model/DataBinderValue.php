<?php

namespace Hazaar\Model;

/**
 * DataBinderValue short summary.
 *
 * DataBinderValue description.
 *
 * @version 1.0
 * @author jamie
 */
class DataBinderValue {

    public $name;

    public $value;

    public $label;

    public $other;

    function __construct($value, $label = null){

        $this->value = $value;

        $this->label = $label;

    }

    /**
     * Get a new dataBinderValue object if the supplied value is valid and non-null
     * 
     * If the value is NULL, then a new dataBinderValue will not be returned.  This method is useful for
     * executing code conditionally if the value is a valid dataBinderValue value.
     * 
     * @param mixed $value 
     * @param mixed $label 
     * @return DataBinderValue|null
     */
    static function create($value, $label = null){

        if($value === null)
            return null;

        if(is_array($value) && array_key_exists('__hz_value', $value) && array_key_exists('__hz_label', $value)){

            $label = $value['__hz_label'];

            $value = $value['__hz_value'];

        }

        return new DataBinderValue($value, $label);

    }

    public function __toString(){

        if($this->name)
            return (string)new \Hazaar\Html\Span(coalesce($this->label, $this->value), array('data-bind' => $this->name));

        return coalesce($this->label, $this->value);

    }

    public function toArray(){

        return array('__hz_value' => $this->value, '__hz_label' => $this->label);

    }

}