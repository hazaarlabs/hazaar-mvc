<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML dt class.
 *
 * @detail      Displays an HTML &lt;dt&gt; element.
 *
 * @since       1.1
 */
class Dt extends Block {

    /**
     * @detail      The HTML dt constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the div.
     */
    function __construct($content = null, $params = []) {

        parent::__construct('dt', $content, $params);

    }

}

