<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML dfn class.
 *
 * @detail      Displays an HTML &lt;dfn&gt; element.
 *
 * @since       1.1
 */
class Dfn extends Block {

    /**
     * @detail      The HTML dfn constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('dfn', $content, $parameters);

    }

}
