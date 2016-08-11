<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML area class.
 *
 * @detail      Displays an HTML &lt;area&gt; element.
 *
 * @since       1.1
 */
class Area extends Inline {

    /**
     * @detail      The HTML anchor constructor.
     *
     * @since       1.1
     *
     * @param       string $href The URL to set as the HREF parameter. (required)
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($href, $coords = null, $parameters = array()) {

        $parameters['href'] = $href;

        if($coords)
            $parameters['coords'] = $coords;

        parent::__construct('area', $parameters);

    }

}
