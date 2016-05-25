<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML p class.
 *
 * @detail      Displays an HTML &lt;p&gt; element.
 *
 * @since       1.1
 */
class P extends Block {

    /**
     * @detail      The HTML p constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('p', $content, $parameters);

    }

}
