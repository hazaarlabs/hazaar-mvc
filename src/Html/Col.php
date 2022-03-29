<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML col class.
 *
 * @detail      Displays an HTML &lt;col&gt; element.
 *
 * @since       1.1
 */
class Col extends Inline {

    /**
     * @detail      The HTML col constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($parameters = []) {

        parent::__construct('col', $parameters);

    }

}
