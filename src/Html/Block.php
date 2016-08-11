<?php

namespace Hazaar\Html;

/**
 * @brief       Block HTML display element
 *
 * @detail      Generic base class for an HTML block element.  This class will render any block element of the defined
 *              type along with any child elements that have been set as it's contents.
 *
 * @since       1.0.0
 */
class Block extends Element implements \ArrayAccess, \Iterator {

    protected $type;

    private   $close;

    private   $content = array();

    /**
     * @detail      The HTML block element constructor.  This allows a block element of any type to be constructed.
     *
     * @since       1.0.0
     *
     * @param       string $type The type of the element.
     *
     * @param       mixed $content Any content to add to the element.  Content can be a string, an integer, another
     *                                 HTML element, or an array of any depth containing a mix of strings and HTML
     *                                 elements.
     *
     * @param       array $parameters An array of parameters to apply to the block element.
     *
     * @param       bool $close Sets whether to close the block.  Sometimes some fancy things need to happen
     *                                 inside a block so we can request that the block no be closed.  This is usually
     *                                 used inside a view to allow a block to be displayed in code, then using '?>'
     *
     * escape sequence to drop the PHP interpreter back into HTML output mode
     *              to render more content that will appear inside the block.
     */
    function __construct($type, $content = NULL, $parameters = array(), $close = TRUE) {

        parent::__construct($type, $parameters);

        if($content !== NULL) {

            if(! is_array($content))
                $content = array($content);

            $this->content = $content;

        }

        $this->close = $close;

    }

    /**
     * @detail      Recursively render a child element.
     *
     * @since       1.0.0
     *
     * @param       mixed $element The element to render.  Can be anything that can be converted to a string, or an
     *                             array of other elements.
     *
     * @return      string
     */
    public function renderElement($element) {

        $out = array();

        if(is_array($element)) {

            foreach($element as $child) {

                $out[] = $this->renderElement($child);

            }

        } else {

            $out[] = (string)$element;

        }

        return implode($out);

    }

    /**
     * @detail      Render the current object as an HTML string.
     *
     * @since       1.0.0
     *
     * @return      string
     */
    public function renderObject() {

        $out = '<' . $this->type . (($this->parameters->count() > 0) ? ' ' . $this->parameters : '');

        $content = array();

        foreach($this->content as $child) {

            $content[] = $this->renderElement($child);

        }

        $out .= '>' . implode($content);

        if($this->close)
            $out .= "</$this->type>";

        return $out;

    }

    /**
     * @detail      Set one or more elements as the contents of the block.
     *
     * @since       1.0.0
     *
     * @return      \\Hazaar\\Html\\Block
     */
    public function set() {

        $this->content = array();

        return self::add(func_get_args());

    }

    /**
     * @detail      Get the contents of the block.
     *
     * @since       2.0.0
     *
     * @return      array
     */
    public function get() {

        return $this->content;

    }

    /**
     * @detail      Add one or more elements to the contents of the block.
     *
     * @since       1.0.0
     *
     * @return      \\Hazaar\\Html\\Block
     */
    public function add() {

        foreach(func_get_args() as $arg) {

            $this->content[] = $arg;

        }

        return $this;

    }

    /**
     * @detail      Prepend an element to the beginning of the contents.
     *
     * @since       1.0.0
     *
     * @return      \\Hazaar\\Html\\Block
     */
    public function prepend($element) {

        array_unshift($this->content, $element);

        return $this;

    }

    public function children() {

        return $this->content;

    }

    public function offsetExists($key) {

        return array_key_exists($key, $this->content);

    }

    public function offsetGet($key) {

        if(array_key_exists($key, $this->content)) {

            return $this->content[$key];

        }

        return NULL;

    }

    public function offsetSet($key, $value) {

        if(is_null($key)) {

            $this->content[] = $value;

        } else {

            $this->content[$key] = $value;

        }

    }

    public function offsetUnset($key) {

        if(array_key_exists($key, $this->content)) {

            unset($this->content[$key]);

        }

    }

    public function rewind() {

        return reset($this->content);

    }

    public function next() {

        return next($this->content);

    }

    public function current() {

        return current($this->content);

    }

    public function valid() {

        return (current($this->content));

    }

    public function key() {

        return key($this->content);

    }

    public function count() {

        return count($this->content);

    }

}
