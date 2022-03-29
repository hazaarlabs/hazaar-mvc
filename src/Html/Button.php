<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML button class.
 *
 * @detail      Displays an HTML &lt;button&gt; element.
 *
 * @since       1.1
 */
class Button extends Block {

    /**
     * @detail      The HTML button constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($label = null, $type = 'button', $parameters = []) {

        if($type)
            $parameters['type'] = $type;

        parent::__construct('button', $label, $parameters);

    }

}
