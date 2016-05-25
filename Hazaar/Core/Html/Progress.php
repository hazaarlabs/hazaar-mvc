<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML progress class.
 *
 * @detail      Displays an HTML &lt;progress&gt; element.
 *
 * @since       1.1
 */
class Progress extends Inline {

    /**
     * @detail      The HTML progress constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($value, $max = 100, $parameters = array()) {

        $parameters['value'] = $value;

        if($max)
            $parameters['max'] = $max;

        parent::__construct('progress', $parameters);

    }

}
