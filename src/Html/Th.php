<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML th class.
 *
 * @detail      Displays an HTML &lt;th&gt; element.
 *
 * @since       1.1
 */
class Th extends Block {

    /**
     * @detail      The HTML th constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('th', $content, $parameters);

    }

}
