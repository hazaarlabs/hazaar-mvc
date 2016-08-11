<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML del class.
 *
 * @detail      Displays an HTML &lt;del&gt; element.
 *
 * @since       1.1
 */
class Del extends Block {

    /**
     * @detail      The HTML del constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('del', $content, $parameters);

    }

}
