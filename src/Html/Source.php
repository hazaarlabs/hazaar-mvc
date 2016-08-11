<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML video source class.
 *
 * @detail      Displays an HTML &lt;source&gt; element.
 *
 * @since       1.1
 */
class Source extends Inline {

    /**
     * @detail      The HTML video source constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($source = null, $type = null, $mime_prefix = null, $parameters = array()) {

        if(!$type && $mime_prefix) {

            $info = pathinfo($source);

            $type = $mime_prefix . '/' . $info['extension'];

        }

        $parameters['src'] = $source;
        
        $parameters['type'] = $type;

        parent::__construct('source', $parameters);

    }

}
