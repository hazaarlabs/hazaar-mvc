<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML nav class.
 *
 * @detail      Displays an HTML &lt;nav&gt; element.
 *
 * @since       1.1
 */
class Nav extends Block {

    /**
     * @detail      The HTML nav constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('nav', $content, $parameters);

    }

}
