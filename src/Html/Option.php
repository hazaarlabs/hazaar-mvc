<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML option class.
 *
 * @detail      Displays an HTML &lt;option&gt; element.
 *
 * @since       1.1
 */
class Option extends Block {

    /**
     * @detail      The HTML option constructor.
     *
     * @since       1.1
     *
     * @param       mixed $value The value of the option element
     *
     * @param       mixed $label The element(s) to set as the content.  Should be a string or integer.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($label, $value = null, $params = array()) {

        if (!is_null($value))
            $params['value'] = $value;

        parent::__construct('option', $label, $params);

    }

}

