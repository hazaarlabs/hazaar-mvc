<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML address class.
 *
 * @detail      Displays an HTML &lt;address&gt; element.
 *
 * @since       1.1
 */
class Address extends Block {

    /**
     * @detail      The HTML address constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $parameters = []) {

        parent::__construct('address', $content, $parameters);

    }

}
