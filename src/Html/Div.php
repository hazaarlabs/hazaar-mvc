<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML div class.
 *
 * @detail      Displays an HTML &lt;div&gt; element.
 *
 * @since       1.1
 */
class Div extends Block {

    /**
     * @detail      The HTML div constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the div.
     */
    function __construct($content = null, $params = array()) {

        parent::__construct('div', $content, $params);

    }

}

