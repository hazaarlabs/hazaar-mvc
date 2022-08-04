<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML canvas class.
 *
 * @detail      Displays an HTML &lt;canvas&gt; element.
 *
 * @since       1.1
 */
class Canvas extends Block {

    /**
     * @detail      The HTML canvas constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($name, $parameters = []) {

        $parameters['id'] = $name;

        parent::__construct('canvas', null, $parameters);

    }

}
