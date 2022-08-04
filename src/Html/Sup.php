<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML superscript class.
 *
 * @detail      Displays an HTML &lt;sup&gt; element.
 *
 * @since       1.1
 */
class Sup extends Block {

    /**
     * @detail      The HTML superscript constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('sup', $content, $parameters);

    }

}
