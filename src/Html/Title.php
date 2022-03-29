<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML title class.
 *
 * @detail      Displays an HTML &lt;title&gt; element.
 *
 * @since       1.1
 */
class Title extends Block {

    /**
     * @detail      The HTML title constructor.
     *
     * @since       1.1
     *
     * @param       string $href The URL to set as the HREF parameter. (required)
     */
    function __construct($content, $parameters = []) {

        parent::__construct('title', $content, $parameters);

    }

}
