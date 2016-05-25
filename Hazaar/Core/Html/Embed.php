<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML embed class.
 *
 * @detail      Displays an HTML &lt;embed&gt; element.
 *
 * @since       1.1
 */
class Embed extends Inline {

    /**
     * @detail      The HTML embed constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($src, $parameters = array()) {

        $parameters['src'] = $src;

        parent::__construct('embed', null, $parameters);

    }

}
