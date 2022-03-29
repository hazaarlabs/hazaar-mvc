<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML strike-through class.
 *
 * @detail      Displays an HTML &lt;s&gt; element.
 *
 * @since       1.1
 */
class S extends Block {

    /**
     * @detail      The HTML strike-through constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('s', $content, $parameters);

    }

}
