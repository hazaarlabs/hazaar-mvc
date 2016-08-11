<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML td class.
 *
 * @detail      Displays an HTML &lt;td&gt; element.
 *
 * @since       1.1
 */
class Td extends Block {

    /**
     * @detail      The HTML td constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('td', $content, $parameters);

    }

}
