<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML rt class.
 *
 * @detail      Displays an HTML &lt;rt&gt; element.
 *
 * @since       1.1
 */
class Rt extends Block {

    /**
     * @detail      The HTML rt constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $parameters = array()) {

        parent::__construct('rt', $content, $parameters);

    }

}
