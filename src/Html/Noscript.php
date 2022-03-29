<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML noscript class.
 *
 * @detail      Displays an HTML &lt;noscript&gt; element.
 *
 * @since       1.1
 */
class Noscript extends Block {

    /**
     * @detail      The HTML noscript constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = []) {

        if(!$content) $content = 'Your browser does not support JavaScript!';
        
        parent::__construct('noscript', $content, $parameters);

    }

}
