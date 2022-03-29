<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML ruby class.
 *
 * @detail      Displays an HTML &lt;ruby&gt; element.
 *
 * @since       1.1
 */
class Ruby extends Block {

    /**
     * @detail      The HTML ruby constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $parameters = []) {

        parent::__construct('ruby', $content, $parameters);

    }

}
