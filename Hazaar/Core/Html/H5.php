<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML header 1 class.
 *
 * @detail      Displays an HTML &lt;h5&gt; element.
 *
 * @since       1.1
 */
class H5 extends Block {

    /**
     * @detail      The HTML header 5 constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('h5', $content, $parameters);

    }

}
