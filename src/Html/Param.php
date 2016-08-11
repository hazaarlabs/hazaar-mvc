<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML param class.
 *
 * @detail      Displays an HTML &lt;param&gt; element.
 *
 * @since       1.1
 */
class Param extends Inline {

    /**
     * @detail      The HTML param constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($name, $value, $parameters = array()) {

        $parameters['name'] = $name;

        $parameters['value'] = (is_bool($value) ? strbool($value) : $value);

        parent::__construct('param', $parameters);

    }

}
