<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML base class.
 *
 * @detail      Displays an HTML &lt;base&gt; element.
 *
 * @since       1.1
 */
class Base extends Inline {

    /**
     * @detail      The HTML base constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($href, $target = null, $parameters = array()) {

        $parameters['href'] = $href;

        if($target)
            $parameters['target'] = $target;

        parent::__construct('base', $parameters);

    }

}
