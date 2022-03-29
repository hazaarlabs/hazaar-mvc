<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML dd class.
 *
 * @detail      Displays an HTML &lt;dd&gt; element.
 *
 * @since       1.1
 */
class Dd extends Block {

    /**
     * @detail      The HTML dd constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the div.
     */
    function __construct($content = null, $params = []) {

        parent::__construct('dd', $content, $params);

    }

}

