<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML dialog class.
 *
 * @detail      Displays an HTML &lt;dialog&gt; element.
 *
 * @since       1.1
 */
class Dialog extends Block {

    /**
     * @detail      The HTML dialog constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $open = false, $parameters = []) {

        if($open)
            $parameters[] = 'open';

        parent::__construct('dialog', $content, $parameters);

    }

}
