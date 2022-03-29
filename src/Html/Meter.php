<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML meter class.
 *
 * @detail      Displays an HTML &lt;meter&gt; element.
 *
 * @since       1.1
 */
class Meter extends Inline {

    /**
     * @detail      The HTML meter constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $value = null, $min = null, $max = null, $parameters = []) {

        if($value)
            $parameters['value'] = (float)$value;

        if($min)
            $parameters['min'] = (float)$min;

        if($max)
            $parameters['max'] = (float)$max;

        parent::__construct('meter', $parameters);

    }

}
