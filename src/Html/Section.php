<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML section class.
 *
 * @detail      Displays an HTML &lt;section&gt; element.
 *
 * @since       1.1
 */
class Section extends Block {

    /**
     * @detail      The HTML section constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $parameters = []) {

        parent::__construct('section', $content, $parameters);

    }

}
