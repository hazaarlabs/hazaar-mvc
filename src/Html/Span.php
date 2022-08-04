<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML span class.
 *
 * @detail      Displays an HTML &lt;span&gt; element.
 *
 * @since       1.1
 */
class Span extends Block {

    /**
     * @detail      The HTML span constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($content = null, $params = []) {

        parent::__construct('span', $content, $params);

    }

}

