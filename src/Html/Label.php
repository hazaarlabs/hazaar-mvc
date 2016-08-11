<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML label class.
 *
 * @detail      Displays an HTML &lt;label&gt; element.
 *
 * @since       1.1
 */
class Label extends Block {

    /**
     * @detail      The HTML form constructor.
     *
     * @since       1.1
     *
     * @param       string $content The content to add to the label
     *
     * @param       string $for The element ID for which the label is for.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $for = null, $parameters = array()) {

        if($for)
            $parameters['for'] = $for;

        parent::__construct('label', $content, $parameters);

    }

}
