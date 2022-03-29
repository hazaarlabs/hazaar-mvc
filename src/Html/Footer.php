<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML footer class.
 *
 * @detail      Displays an HTML &lt;footer&gt; element.
 *
 * @since       1.1
 */
class Footer extends Block {

    /**
     * @detail      The HTML footer constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('footer', $content, $parameters);

    }

}
