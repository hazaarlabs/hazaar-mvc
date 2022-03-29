<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML summary class.
 *
 * @detail      Displays an HTML &lt;summary&gt; element.
 *
 * @since       1.1
 */
class Summary extends Block {

    /**
     * @detail      The HTML summary constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($content = null, $params = []) {

        parent::__construct('summary', $content, $params);

    }

}

