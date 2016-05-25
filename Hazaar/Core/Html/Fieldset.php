<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML fieldset class.
 *
 * @detail      Displays an HTML &lt;fieldset&gt; element.
 *
 * @since       1.1
 */
class Fieldset extends Block {

    /**
     * @detail      The HTML fieldset constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('fieldset', $content, $parameters);

    }

}
