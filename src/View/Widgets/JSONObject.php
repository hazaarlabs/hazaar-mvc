<?php

namespace Hazaar\View\Widgets;

/**
 * @brief       A JSON encodable object.
 *
 * @detail      When rendered this object will output a string of JSON code that can be used
 *              by any client-side JavaScript code.  It supports all data types including
 *              arrays, objects, strings, numbers as well as functions.
 *
 * @since       1.1
 */
class JSONObject extends \Hazaar\View\ViewableObject {

    private $properties = array();

    /**
     * @detail      JSON Object Constructor
     *
     * @param       array $properties Optional array of properties to populate the object with.
     */
    function __construct($properties = array()) {

        $this->properties = $properties;

    }

    /**
     * @detail      Render the object into encoded JSON output.
     *
     * @return      string
     */
    public function renderObject() {

        return $this->encode($this->properties);

    }

    public function renderProperties($items = null) {

        if(!$items)
            $items = $this->properties;

        return $this->encode($items, true);

    }

    /**
     * @detail      Encodes an item or an array of items into an object or array depending on
     *              whether the source array is an associative array or numeric array.
     *
     * @param       mixed $items A item or array of items to encode.
     *
     * @return      string A JSON encoded representation of the item(s).
     */
    private function encode($items, $properties_only = false) {

        if(!is_array($items))
            return $this->encodeItem($items);

        if(!$properties_only) {

            $assoc = is_assoc($items);

            list($in, $out) = ($assoc ? array(
                '{ ',
                ' }'
            ) : array(
                '[ ',
                ' ]'
            ));

        } else {

            $assoc = false;

            $in = '';

            $out = '';

        }

        $json = array();

        foreach($items as $key => $value) {

            $json[] = ($assoc ? $key . ' : ' : null) . $this->encodeItem($value);

        }

        return $in . implode(', ', $json) . $out;

    }

    /**
     * @detail      Encode a single item into it's JSON representation.  Supported argument types are:
     *              int, float, bool, ViewableObject, null and strings.  ViewableObject objects will be
     *              rendered (by calling their render() method) and their output used as is.
     *
     * @param       mixed $item The item to encode.
     *
     * @return      string The JSON representation of the item.
     */
    private function encodeItem($item) {

        if(is_array($item)) {

            $item = $this->encode($item);

        } elseif($item instanceof \Hazaar\View\ViewableObject) {

            /**
             * Renders the object.  For JavaScript objects the 'true' argument says to use an anonymous function
             * container.
             */
            $item = $item->renderObject();

        } elseif(is_bool($item)) {

            $item = ($item ? 'true' : 'false');

        } elseif(is_int($item) || is_float($item)) {

            /**
             * Do Nothing;
             */

        } elseif(is_null($item)) {

            $item = 'null';

        } else {

            if(substr($item, 0, 1) == '!') {

                $item = substr($item, 1);

            } else {

                $item = '"' . addslashes($item) . '"';

            }

        }

        return $item;

    }

    /**
     * @detail      Returns an array of properties currently set.
     *
     * @return      array
     */
    public function properties() {

        return $this->properties;

    }

    /**
     * @detail      Returns the number of properties currently set.
     *
     * @return      int
     */
    public function count() {

        return count($this->properties);

    }

    /**
     * @detail      Add an item to a property array.
     *
     * @return      Hazaar\\Widgets\\JSONObject
     */
    public function add($key, $items) {

        /**
         * Only continue if we have items to add, and the property either doesn't exist, or exists and is an array.
         */
        if(!$items || (array_key_exists($key, $this->properties) && !is_array($this->properties[$key])))
            return false;

        $this->properties[$key][] = $items;

        return $this;

    }

    /**
     * @detail      Set a single property
     * @return      Hazaar\\Widgets\\JSONObject
     */

    public function set($key, $value, $type = null) {

        if(!is_array($value) && !$value instanceof JavaScript && $type && substr($value, 0, 1) !== '!' && $value !== null)
            settype($value, $type);

        $this->properties[$key] = $value;

        return $this;

    }

    /**
     * @detail      Get and existing property.  If the property doesn't exist, null will be returned.
     *
     * @return      mixed The requested property.
     */
    public function get($key, $encoded = false) {

        $value = (array_key_exists($key, $this->properties) ? $this->properties[$key] : null);

        if($encoded)
            return $this->encodeItem($value);

        return $value;

    }

    /**
     * @detail      Add a JavaScript function to the list of properties.  If the $code argument is just
     *              a string it will be converted to a JavaScript object.
     *
     * @return      Hazaar\\Widgets\\JSONObject
     */
    public function setFunction($key, $code, $argdef = array()) {

        if(!$code instanceof JavaScript)
            $code = new JavaScript($code, $argdef);

        return $this->set($key, $code);

    }

}
