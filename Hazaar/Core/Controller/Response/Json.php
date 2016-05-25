<?php

namespace Hazaar\Controller\Response;

class Json extends \Hazaar\Controller\Response implements \ArrayAccess {

    private $data = array();

    /*
     * If the callback is set, such as in a JSONP request, we use the callback to return
     * the encoded data.
     */
    private $callback;

    function __construct($data = array(), $status = 200) {

        if(! function_exists('json_encode')) {
            throw new Exception\JsonNotSupported();
        }

        parent::__construct("application/json", $status);

        $this->data = $data;

    }

    public function toArray() {

        return $this->data;

    }

    public function & __get($key) {

        return $this->get($key);

    }

    public function & get($key) {

        return $this->data[$key];

    }

    public function __set($key, $value) {

        $this->set($key, $value);

    }

    public function set($key, $value) {

        $this->data[$key] = $value;

    }

    public function populate($data) {

        $this->data = $data;

    }

    public function push($data) {

        $this->data[] = $data;

    }

    /*
     * JSONP Tools
     */

    public function setCallback($callback) {

        $this->callback = $callback;

    }

    public function getContent() {

        $data = json_encode($this->data);

        if($this->callback) {

            $data = $this->callback . "($data)";

        }

        return $data;

    }

    //ArrayAccess

    public function offsetExists($key) {

        return array_key_exists($key, $this->data);

    }

    public function & offsetGet($key) {

        if(array_key_exists($key, $this->data))
            return $this->data[$key];

        $null = NULL;

        return $null;

    }

    public function offsetSet($key, $value) {

        if($key === NULL)
            $this->data[] = $value;
        else
            $this->data[$key] = $value;

    }

    public function offsetUnset($key) {

        unset($this->data[$key]);

    }

}