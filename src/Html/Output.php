<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML output class.
 *
 * @detail      Displays an HTML &lt;output&gt; element.
 *
 * @since       1.1
 */
class Output extends Block {

    /**
     * @detail      The HTML output constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($name, $for, $parameters = []) {

        $parameters['name'] = $name;

        $parameters['for'] = $for;

        parent::__construct('output', null, $parameters);

    }

}
