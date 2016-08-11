<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML article class.
 *
 * @detail      Displays an HTML &lt;article&gt; element.
 *
 * @since       1.1
 */
class Article extends Block {

    /**
     * @detail      The HTML article constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     * 
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content, $parameters = array()) {

        parent::__construct('article', $content, $parameters);

    }

}
