<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML header class.
 *
 * @detail      Displays an HTML &lt;header&gt; element.
 *
 * @since       1.1
 */
class Header extends Block {

    /**
     * @detail      The HTML header constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('header', $content, $parameters);

    }

}
