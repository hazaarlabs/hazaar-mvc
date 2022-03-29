<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML code class.
 *
 * @detail      Displays an HTML &lt;code&gt; element.
 *
 * @since       1.1
 */
class Code extends Block {

    /**
     * @detail      The HTML code constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        parent::__construct('code', $content, $parameters);

    }

}
