<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML body class.
 *
 * @detail      Displays an HTML &lt;body&gt; element.
 *
 * @since       1.1
 */
class Body extends Block {

    /**
     * @detail      The HTML body constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('body', $content, $parameters);

    }

}
