<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML sub class.
 *
 * @detail      Displays an HTML &lt;sub&gt; element.
 *
 * @since       1.1
 */
class Sub extends Block {

    /**
     * @detail      The HTML sub constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($content = null, $params = array()) {

        parent::__construct('sub', $content, $params);

    }

}

