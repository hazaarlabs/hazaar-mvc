<?php

namespace Hazaar\Html;

/**
 * @brief       HTML Parameter Class
 *
 * @detail      Class for storing and rendering HTML element parameters.
 *
 * @since       1.0.0
 */
class Parameters implements \Countable {

    private $params       = array();

    private $delimeter    = '=';

    private $suffix;

    private $quote_params = TRUE;

    /**
     * @detail      HTML parameter class constructor
     *
     * @since       1.0.0
     *
     * @param       Array $params Array of key/value pairs of parameters
     *
     * @param       string $delimiter Optionally set the delimiter. (allows for use in other than HTML)
     *
     * @param       bool $quote_params Set whether parameters are quoted.  Default: true.
     *
     * @param       string $suffix Optionally specify a parameter value suffix.
     */
    function __construct($params = array(), $delimeter = NULL, $quote_params = TRUE, $suffix = NULL) {

        if($params) {

            if(! is_array($params))
                $params = array($params);

            $this->params = $params;

        }

        if($delimeter)
            $this->delimeter = $delimeter;

        if($quote_params !== NULL)
            $this->quote_params = $quote_params;

        if($suffix)
            $this->suffix = $suffix;

    }

    public function count() {

        return count($this->params);

    }

    public function & get($key) {

        if(array_key_exists($key, $this->params))
            return $this->params[$key];

        $null = NULL;

        return $null;

    }

    public function set() {

        if(func_num_args() == 1) {

            list($key) = func_get_args();

            $value = NULL;

        } else {

            list($key, $value) = func_get_args();

        }

        if($value === NULL) {

            return $this->params[] = $key;

        } elseif(is_bool($value)) {

            $value = strbool($value);

        } elseif(is_array($value)) {

            foreach($value as $skey => $svalue)
                $this->params[$skey] = $svalue;

            return NULL;

        }

        return $this->params[$key] = $value;

    }

    public function appendTo($key, $value) {

        if(! array_key_exists($key, $this->params))
            return FALSE;

        $this->params[$key] .= $value;

        return TRUE;

    }

    public function __tostring() {

        return $this->renderObject();

    }

    public function renderObject() {

        $out = array();

        foreach($this->params as $key => $value) {

            if($value instanceof Style) {

                $value = (string)$value->asParameterList();

                $key = 'style';

            }

            if(is_numeric($key)) {

                $out[] = $value;

            } else {

                if($this->quote_params)
                    $value = '"' . htmlspecialchars($value) . '"';

                $out[] = $key . $this->delimeter . $value . $this->suffix;

            }

        }

        return implode(' ', $out);

    }

    public function has($key) {

        return array_key_exists($key, $this->params);

    }

    public function append($key, $value) {

        if(array_key_exists($key, $this->params))
            $this->params[$key] .= $value;
        else
            $this->params[$key] = $value;

    }

}

