<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML kbd class.
 *
 * @detail      Displays an HTML &lt;kbd&gt; element.
 *
 * @since       1.1
 */
class Kbd extends Block {

    /**
     * @detail      The HTML kbd constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('kbd', $content, $parameters);

    }

}
