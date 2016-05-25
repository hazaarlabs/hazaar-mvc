<?php

namespace Hazaar\Http\WebDAV;

class Property implements \ArrayAccess {

    private $namespace;

    private $attributes = array();

    private $elements = array();

    function __construct(\DOMElement $dom = null) {

        $this->namespace = $dom->namespaceURI;

        foreach($dom->childNodes as $node) {

            if($node->nodeType == XML_ELEMENT_NODE) {

                if($node->attributes->length > 0) {

                    foreach($node->attributes as $attr) {

                        $this->attributes[$attr->localName] = $attr->textContent;

                    }

                }

                if($node->childNodes->length == 1 && $node->childNodes->item(0)->nodeType !== XML_ELEMENT_NODE) {

                    $this->elements[$node->localName] = $node->textContent;

                } else {

                    $this->elements[$node->localName] = new Property($node);

                }

            } else {

                $this->elements = $node->textContent;

            }

        }

    }

    public function namespaceURI() {

        return $this->namespace;

    }

    public function __tostring() {

        if(! is_array($this->elements))
            return $this->elements;

        return '';

    }

    public function get($key) {

        if(array_key_exists($key, $this->elements))
            return $this->elements[$key];

        return new Property();
    }

    public function __get($key) {

        return $this->get($this->fkey($key));

    }

    public function __set($key, $value) {

        if(! $value instanceof Property)
            $value = new Property($value);

        $this->elements[$key] = $value;

    }

    private function fkey($key) {

        return str_replace('_', '-', $key);

    }

    public function has($key) {

        return array_key_exists($this->fkey($key), $this->elements);

    }

    public function offsetExists($offset) {

        return array_key_exists($this->fkey($offset), $this->attributes);

    }

    public function offsetGet($offset) {

        $offset = $this->fkey($offset);

        if(array_key_exists($offset, $this->attributes))
            return $this->attributes[$offset];

        return null;

    }

    public function offsetSet($offset, $value) {

        $this->attributes[$this->fkey($offset)] = $value;

    }

    public function offsetUnset($offset) {

        $offset = $this->fkey($offset);

        if(array_key_exists($offset, $this->attributes))
            unset($this->attributes[$offset]);

    }

}

