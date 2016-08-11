<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML italic class.
 *
 * @detail      Displays an HTML &lt;i&gt; element.
 *
 * @since       1.1
 */
class I extends Block {

    /**
     * @detail      The HTML italic constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('i', $content, $parameters);

    }

}
